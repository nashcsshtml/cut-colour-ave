<?php

namespace AmeliaBooking\Infrastructure\WP\Integrations\IvyForms;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use IvyForms\Controllers\Entry\GetEntryController;
use IvyForms\Controllers\Form\FormSubmissionController;
use IvyForms\Controllers\Form\ValidateBeforeSubmitController;
use IvyForms\Plugin\Plugin;
use IvyForms\Services\API\IvyFormsAPI;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class IvyFormsService
 *
 * @package AmeliaBooking\Infrastructure\WP\Integrations\IvyForms
 */
class IvyFormsService
{
    private const IVYFORMS_PLUGIN_FILE = 'ivyforms/ivyforms.php';

    /**
     * Shortcode handler
     *
     * @param string $id
     * @return string
     */
    public static function shortcode(string $id): string
    {
        $form = self::getForm(intval($id));

        return !empty($form['published']) ? do_shortcode('[ivyforms id=' . esc_attr($form['id']) . ']') : '';
    }

    /**
     * Get a single form (same source as IvyForms REST GET /ivyforms/v1/form/{id}), without HTTP.
     *
     * @return array<string, mixed>
     */
    public static function getForm(int $id): array
    {
        if (!self::isIvyFormsApiAvailable() || $id <= 0) {
            return [];
        }

        $form = IvyFormsAPI::getForm($id);

        if (is_wp_error($form) || !is_object($form) || !method_exists($form, 'toArray')) {
            return [];
        }

        $formArray = $form->toArray();

        return is_array($formArray) ? $formArray : [];
    }

    /**
     * Get forms (same source as IvyForms REST GET /ivyforms/v1/form), without HTTP.
     *
     * Uses {@see IvyFormsAPI::getForms()} when IvyForms is active; otherwise returns [].
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getForms(): array
    {
        if (!self::isIvyFormsApiAvailable()) {
            return [];
        }

        $result = IvyFormsAPI::getForms();

        if (is_wp_error($result) || !is_array($result)) {
            return [];
        }

        $forms = [];

        foreach ($result as $form) {
            if (is_object($form) && method_exists($form, 'getId') && method_exists($form, 'getName')) {
                $forms[] = [
                    'value' => $form->getId(),
                    'label' => $form->getName() . ' (id: ' . $form->getId() . ')',
                ];
            }
        }

        return $forms;
    }

    /**
     * Get form fields (same source as IvyForms REST fields for a form), without HTTP.
     *
     * @return array<int, array{id: int, index: int, key: string, type: string, label: string}>
     */
    public static function getFormFields(int $formId): array
    {
        if (!self::isIvyFormsApiAvailable() || $formId <= 0) {
            return [];
        }

        $fields = IvyFormsAPI::getFields($formId);

        if (is_wp_error($fields) || !is_array($fields)) {
            return [];
        }

        $eligibleFields = [];

        foreach ($fields as $field) {
            if (!is_object($field) || !method_exists($field, 'toArray')) {
                continue;
            }

            $fieldArray = $field->toArray();
            $id = isset($fieldArray['id']) ? (int) $fieldArray['id'] : 0;

            if ($id <= 0 || !in_array($fieldArray['type'], ['email', 'text', 'phone'], true)) {
                continue;
            }

            $fieldIndex = $fieldArray['fieldIndex'] ?? null;

            if ($fieldIndex === null || $fieldIndex === '' || !is_numeric($fieldIndex) || (int) $fieldIndex < 0) {
                continue;
            }

            $parentId = isset($fieldArray['parentId']) && $fieldArray['parentId'] !== '' && $fieldArray['parentId'] !== null
                ? (int) $fieldArray['parentId']
                : null;

            $eligibleFields[] = [
                'id'         => $id,
                'index'      => (int) $fieldIndex,
                'parentId'   => $parentId,
                'fieldArray' => $fieldArray,
            ];
        }

        $generatedNameFieldKeys = self::buildGeneratedNameFieldKeys($eligibleFields);

        $result = [];

        foreach ($eligibleFields as $eligibleField) {
            $fieldArray = $eligibleField['fieldArray'];
            $key = '';

            if ($eligibleField['parentId'] !== null) {
                $nameFieldType = $fieldArray['nameFieldType'] ?? '';
                $key = is_string($nameFieldType) && $nameFieldType !== ''
                    ? $nameFieldType
                    : ($generatedNameFieldKeys[$eligibleField['id']] ?? '');
            }

            $result[] = [
                'id'    => $eligibleField['id'],
                'index' => $eligibleField['index'],
                'key'   => $key,
                'type'  => $eligibleField['parentId'] !== null ? 'name' : $fieldArray['type'],
                'label' => (string) ($fieldArray['label'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Assign nameField1, nameField2, ... to name subfields missing a stored nameFieldType.
     * Children are grouped by parentId and ordered by field ID.
     *
     * @param array<int, array{id: int, parentId: int|null}> $eligibleFields
     *
     * @return array<int, string>
     */
    private static function buildGeneratedNameFieldKeys(array $eligibleFields): array
    {
        $childrenByParent = [];

        foreach ($eligibleFields as $eligibleField) {
            if ($eligibleField['parentId'] === null) {
                continue;
            }

            $childrenByParent[$eligibleField['parentId']][] = $eligibleField['id'];
        }

        $generatedKeys = [];

        foreach ($childrenByParent as $childIds) {
            sort($childIds, SORT_NUMERIC);

            foreach ($childIds as $position => $childId) {
                $generatedKeys[$childId] = 'nameField' . ($position + 1);
            }
        }

        return $generatedKeys;
    }

    /**
     * Get entry fields
     *
     * @return array<int, array{id: int, label: string, value: mixed}>
     */
    public static function getEntryFields(int $entryId): array
    {
        if (!self::isIvyFormsApiAvailable() || $entryId <= 0) {
            return [];
        }

        $plugin = Plugin::getInstance();

        if (!$plugin instanceof Plugin || $plugin->container === null) {
            return [];
        }

        try {
            /** @var GetEntryController $controller */
            $controller = $plugin->container->get(GetEntryController::class);
        } catch (\Throwable $exception) {
            return [];
        }

        $request = new WP_REST_Request('GET', '/ivyforms/v1/entry/' . $entryId);
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_param('id', $entryId);

        $response = $controller($request);

        $entryResult = self::normalizeGetEntryResponse($response);

        if (empty($entryResult['entry']['formId'])) {
            return [];
        }

        $entryFields = $entryResult['fields'] ?? null;

        if (!is_array($entryFields)) {
            return [];
        }

        $entry = array_column($entryFields, 'fieldValue', 'fieldId');

        $fields = IvyFormsAPI::getFields($entryResult['entry']['formId']);

        if (is_wp_error($fields) || !is_array($fields)) {
            return [];
        }

        $result = [];

        foreach ($fields as $field) {
            if (!is_object($field) || !method_exists($field, 'toArray')) {
                continue;
            }

            $fieldArray = $field->toArray();

            $fieldId = $fieldArray['id'] ?? null;
            if ($fieldId === null || !isset($entry[$fieldId])) {
                continue;
            }

            $fieldValue = '';

            switch ($field->getType()) {
                case 'checkbox':
                case 'multi-select':
                case 'radio':
                case 'select':
                    $fieldOptions = IvyFormsAPI::getFieldOptions($field->getId());
                    $fieldValues = [];

                    if (!is_wp_error($fieldOptions) && is_array($fieldOptions)) {
                        $rawValue = $entry[$fieldId];

                        $entriesValues = is_array($rawValue)
                            ? $rawValue
                            : array_filter(array_map('trim', explode(',', (string) $rawValue)));

                        foreach ($entriesValues as $entryValue) {
                            foreach ($fieldOptions as $option) {
                                if (!is_object($option) || !method_exists($option, 'getValue') || !method_exists($option, 'getLabel')) {
                                    continue;
                                }

                                $optionValue = $option->getValue();

                                if ($optionValue == $entryValue) {
                                    $fieldValues[] = $option->getLabel();
                                }
                            }
                        }
                    }

                    $fieldValue = implode(', ', $fieldValues);

                    break;
                default:
                    $fieldValue = $entry[$fieldId];

                    break;
            }

            $result[] = [
                'id'    => $fieldArray['id'],
                'label' => $fieldArray['label'],
                'value' => $fieldValue,
            ];
        }

        return $result;
    }

    /**
     * Ensure IvyForms is bootstrapped (e.g. Elementor editor AJAX may not load all plugins).
     */
    private static function isIvyFormsApiAvailable(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (!is_plugin_active(self::IVYFORMS_PLUGIN_FILE)) {
            return false;
        }

        if (!class_exists(IvyFormsAPI::class)) {
            $pluginPath = WP_PLUGIN_DIR . '/' . self::IVYFORMS_PLUGIN_FILE;

            if (!is_readable($pluginPath)) {
                return false;
            }

            // Optional plugin file; path exists only when IvyForms is installed alongside Amelia.
            /** @phpstan-ignore includeOnce.fileNotFound */
            include_once $pluginPath;
        }

        return class_exists(IvyFormsAPI::class);
    }

    /**
     * Validate IvyForms entry via the same handler as REST POST /ivyforms/v1/form/validate_before_submit/.
     *
     * Expected shape (from IvyForms public form submit): formId, values, optional postId, pageId and referer.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function validateBeforeSubmit(array $data): void
    {
        if (!self::isIvyFormsApiAvailable()) {
            throw new InvalidArgumentException('IvyForms is not available.');
        }

        $formId = isset($data['formId']) ? (int) $data['formId'] : 0;
        $values = $data['values'] ?? null;

        if ($formId <= 0 || !is_array($values)) {
            throw new InvalidArgumentException('Invalid IvyForms entry data.');
        }

        $plugin = Plugin::getInstance();

        if (!$plugin instanceof Plugin || $plugin->container === null) {
            throw new InvalidArgumentException('IvyForms plugin is not initialized.');
        }

        try {
            /** @var ValidateBeforeSubmitController $controller */
            $controller = $plugin->container->get(ValidateBeforeSubmitController::class);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('IvyForms validate before submit is unavailable.');
        }

        $payload = [
            'formId' => $formId,
            'values' => $values,
            'nonce'  => wp_create_nonce('ivyformsFrontSubmissionNonce_' . $formId),
        ];

        if (isset($data['postId'])) {
            $payload['postId'] = (int) $data['postId'];
        }

        if (!empty($data['pageId']) && is_string($data['pageId'])) {
            $payload['pageId'] = $data['pageId'];
        }

        if (!empty($data['referer']) && is_string($data['referer'])) {
            $payload['referer'] = $data['referer'];
        }

        $request = new WP_REST_Request('POST', '/ivyforms/v1/form/validate_before_submit/');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params($payload);

        $response = $controller($request);
        $body = $response->get_data();

        if ($response->get_status() >= 400 || !is_array($body)) {
            $message = is_array($body)
                ? (string) ($body['message'] ?? 'IvyForms validation failed.')
                : 'IvyForms validation failed.';

            throw new InvalidArgumentException($message);
        }

        $validationData = $body['data']['data'] ?? null;

        if (!is_array($validationData) || empty($validationData['valid'])) {
            throw new InvalidArgumentException('IvyForms entry validation failed.');
        }
    }

    /**
     * Submit an IvyForms entry via the same handler as REST POST /ivyforms/v1/form/submission/.
     *
     * Expected shape (from IvyForms public form submit): formId, values, optional postId and referer.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function addFormEntry(array $data): array
    {
        if (!self::isIvyFormsApiAvailable()) {
            return [];
        }

        $formId = isset($data['formId']) ? (int) $data['formId'] : 0;
        $values = $data['values'] ?? null;

        if ($formId <= 0 || !is_array($values)) {
            return [];
        }

        $plugin = Plugin::getInstance();

        if (!$plugin instanceof Plugin || $plugin->container === null) {
            return [];
        }

        try {
            /** @var FormSubmissionController $controller */
            $controller = $plugin->container->get(FormSubmissionController::class);
        } catch (\Throwable $exception) {
            return [];
        }

        $payload = [
            'formId' => $formId,
            'values' => $values,
            'nonce'  => wp_create_nonce('ivyformsFrontSubmissionNonce_' . $formId),
        ];

        if (isset($data['postId'])) {
            $payload['postId'] = (int) $data['postId'];
        }

        if (!empty($data['referer']) && is_string($data['referer'])) {
            $payload['referer'] = $data['referer'];
        }

        $request = new WP_REST_Request('POST', '/ivyforms/v1/form/submission/');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params($payload);

        $response = $controller($request);

        return self::normalizeSubmissionResponse($response);
    }

    /**
     * @param WP_REST_Response $response
     *
     * @return array<string, mixed>
     */
    private static function normalizeSubmissionResponse(WP_REST_Response $response): array
    {
        $body = $response->get_data();

        if (!is_array($body)) {
            return [];
        }

        if ($response->get_status() >= 400) {
            return [
                'success' => false,
                'status'  => $response->get_status(),
                'message' => $body['message'] ?? '',
                'data'    => $body['data'] ?? [],
            ];
        }

        return $body;
    }

    /**
     * @param WP_REST_Response $response
     *
     * @return array{entry?: array<string, mixed>, fields?: array<int, mixed>}
     */
    private static function normalizeGetEntryResponse(WP_REST_Response $response): array
    {
        $body = $response->get_data();

        if (!is_array($body) || $response->get_status() >= 400) {
            return [];
        }

        $data = $body['data'] ?? null;

        return is_array($data) ? $data : [];
    }
}
