(function (wp) {
  var el = wp.element.createElement
  var components = wp.components
  var blockControls = wp.blockEditor.BlockControls
  var inspectorControls = wp.blockEditor.InspectorControls
  var InnerBlocks = wp.blockEditor.InnerBlocks
  var useBlockProps = wp.blockEditor.useBlockProps
  var addFilter = wp.hooks ? wp.hooks.addFilter : null
  var createHigherOrderComponent = wp.compose ? wp.compose.createHigherOrderComponent : null
  var useSelect = wp.data ? wp.data.useSelect : null
  var useDispatch = wp.data ? wp.data.useDispatch : null
  var Fragment = wp.element.Fragment
  var useEffect = wp.element.useEffect
  var wpAmeliaLabels = window.wpAmeliaLabels || {}
  var data = wpAmeliaLabels.data || {
    categories: [],
    servicesList: [],
    employees: [],
    locations: [],
    packages: []
  }

  function getLabel (key, fallback) {
    return wpAmeliaLabels[key] || fallback
  }

  var categories = []
  var services = []
  var employees = []
  var locations = []
  var packages = []

  var blockStyle = {
    color: 'red'
  }

  categories = (data.categories ?? []).map((c) => ({
    value: c.id,
    text: `${c.name} (id: ${c.id})`,
  }))

  services = (data.servicesList ?? []).map((s) => ({
    value: s.id,
    text: `${s.name} (id: ${s.id})`,
  }))

  employees = (data.employees ?? []).map((e) => ({
    value: e.id,
    text: `${e.firstName} ${e.lastName} (id: ${e.id})`,
  }))

  locations = (data.locations ?? []).map((l) => ({
    value: l.id,
    text: `${l.name} (id: ${l.id})`,
  }))

  packages = (data.packages ?? []).map((p) => ({
    value: p.id,
    text: `${p.name} (id: ${p.id})`,
  }))

  function getSelectOptions (items, defaultLabel) {
    var options = [{value: '', label: defaultLabel}]

    Object.keys(items)
      .map(function (key) {
        return items[key]
      })
      .sort(function (a, b) {
        if (parseInt(a.pos) < parseInt(b.pos)) return -1
        if (parseInt(a.pos) > parseInt(b.pos)) return 1
        return 0
      })
      .forEach(function (item) {
        options.push({value: item.value, label: item.text})
      })

    return options
  }

  function getShortcodeOptionsConfig () {
    return {
      show: [{value: '', label: getLabel('show_all', 'Show all')}, {value: 'services', label: getLabel('services', 'Services')}, {value: 'packages', label: getLabel('packages', 'Packages')}],
      trigger_type: [{value: 'id', label: getLabel('trigger_type_id', 'ID')}, {value: 'class', label: getLabel('trigger_type_class', 'Class')}],
      layout: [{value: '1', label: getLabel('layout_dropdown', 'Dropdown layout')}, {value: '2', label: getLabel('layout_list', 'List layout')}],
      categories: getSelectOptions(categories, getLabel('show_all_categories', 'Show all categories')),
      services: getSelectOptions(services, getLabel('show_all_services', 'Show all services')),
      employees: getSelectOptions(employees, getLabel('show_all_employees', 'Show all employees')),
      locations: getSelectOptions(locations, getLabel('show_all_locations', 'Show all locations')),
      packages: getSelectOptions(packages, getLabel('show_all_packages', 'Show all packages'))
    }
  }

  function renderShortcodeSettingsControls (attributes, setAttrs, options) {
    return [
      el(components.PanelRow,
        {},
        el('label', {htmlFor: 'amelia-js-parametars'}, getLabel('filter', 'Preselect Booking Parameters')),
        el(components.FormToggle, {
          id: 'amelia-js-parametars',
          checked: attributes.parametars,
          onChange: function () {
            setAttrs({parametars: !attributes.parametars})
          }
        })
      ),
      el('div', {style: {'margin-bottom': '1em'}}, ''),
      attributes.parametars ? el('div', {className: 'amelia-gutenberg-multi-select-note'}, getLabel('multiselect_note', 'For multiselect: hold CTRL / Command (Cmd).')) : null,
      attributes.parametars && categories.length > 1 ? el(components.SelectControl, {
        id: 'amelia-js-select-category',
        className: 'amelia-gutenberg-multi-select',
        label: getLabel('select_category', 'Select category'),
        value: attributes.category,
        options: options.categories,
        multiple: true,
        onChange: function (value) {
          setAttrs({category: value})
        }
      }) : null,
      attributes.parametars && services.length > 1 ? el(components.SelectControl, {
        id: 'amelia-js-select-service',
        className: 'amelia-gutenberg-multi-select',
        label: getLabel('select_service', 'Select service'),
        value: attributes.service,
        options: options.services,
        multiple: true,
        onChange: function (value) {
          setAttrs({service: value})
        }
      }) : null,
      attributes.parametars && employees.length > 1 ? el(components.SelectControl, {
        id: 'amelia-js-select-employee',
        className: 'amelia-gutenberg-multi-select',
        label: getLabel('select_employee', 'Select employee'),
        value: attributes.employee,
        options: options.employees,
        multiple: true,
        onChange: function (value) {
          setAttrs({employee: value})
        }
      }) : null,
      attributes.parametars && locations.length > 1  ? el(components.SelectControl, {
        id: 'amelia-js-select-location',
        className: 'amelia-gutenberg-multi-select',
        label: getLabel('select_location', 'Select location'),
        value: attributes.location,
        options: options.locations,
        multiple: true,
        onChange: function (value) {
          setAttrs({location: value})
        }
      }) : null,
      attributes.parametars && packages.length ? el(components.SelectControl, {
        id: 'amelia-js-select-package',
        className: 'amelia-gutenberg-multi-select',
        label: getLabel('select_package', 'Select package'),
        value: attributes.package,
        options: options.packages,
        multiple: true,
        onChange: function (value) {
          setAttrs({package: value})
        }
      }) : null,
      attributes.parametars && packages.length ? el(components.SelectControl, {
        label: getLabel('show_all', 'Show all'),
        value: attributes.show,
        options: options.show,
        onChange: function (value) {
          setAttrs({show: value})
        }
      }) : null,
      el(components.SelectControl, {
        label: getLabel('layout_select_label', 'Choose layout version'),
        value: attributes.layout,
        options: options.layout,
        onChange: function (value) {
          setAttrs({layout: value})
        }
      }),
    ]
  }

  var withAmeliaStepBookingButtonControls = createHigherOrderComponent
    ? createHigherOrderComponent(function (BlockEdit) {
      return function (props) {
        var isSupportedButtonBlock = props.name === 'core/button' || props.name === 'core/buttons'

        if (!isSupportedButtonBlock || !useSelect || !useDispatch) {
          return el(BlockEdit, props)
        }

        var targetClientId = useSelect(function (select) {
          var blockEditorSelect = select('core/block-editor')
          var cursor = props.clientId

          while (cursor) {
            var block = blockEditorSelect.getBlock(cursor)
            if (block && block.name === 'amelia/step-booking-button-gutenberg-block') {
              return cursor
            }

            cursor = blockEditorSelect.getBlockRootClientId(cursor)
          }

          return null
        }, [props.clientId])

        var targetAttributes = useSelect(function (select) {
          if (!targetClientId) {
            return null
          }

          var block = select('core/block-editor').getBlock(targetClientId)
          return block ? block.attributes : null
        }, [targetClientId])

        if (!targetClientId || !targetAttributes) {
          return el(BlockEdit, props)
        }

        var updateBlockAttributes = useDispatch('core/block-editor').updateBlockAttributes
        var options = getShortcodeOptionsConfig()

        function setAttrs (nextAttributes) {
          updateBlockAttributes(targetClientId, nextAttributes)
        }

        return el(Fragment, {},
          el(BlockEdit, props),
          el(inspectorControls, {},
            el(
              components.PanelBody,
              {title: getLabel('step_booking_shortcode', 'Amelia Step Booking Shortcode'), initialOpen: true},
              renderShortcodeSettingsControls(targetAttributes, setAttrs, options)
            )
          )
        )
      }
    }, 'withAmeliaStepBookingButtonControls')
    : null

  var stepBookingBlockMeta = wpAmeliaLabels && wpAmeliaLabels.step_booking_button_gutenberg_block
    ? wpAmeliaLabels.step_booking_button_gutenberg_block
    : {
        title: 'Amelia - Booking Button',
        description: 'Step-by-Step booking view guides the customers through several steps in order to make their bookings.'
      }

  // Registering the Block for booking shotcode
  wp.blocks.registerBlockType('amelia/step-booking-button-gutenberg-block', {
    apiVersion: 3,
    title: stepBookingBlockMeta.title,
    description: stepBookingBlockMeta.description,
    icon: window.ameliaBlockIcon,
    category: 'amelia-blocks',
    keywords: [
      'amelia',
      'booking'
    ],
    supports: {
      customClassName: false,
      html: false
    },
    attributes: {
      short_code: {
        type: 'string',
        default: '[ameliastepbooking]'
      },
      trigger: {
        type: 'string',
        default: ''
      },
      trigger_type: {
        type: 'string',
        default: 'id'
      },
      in_dialog: {
        type: 'boolean',
        default: true
      },
      show: {
        type: 'string',
        default: ''
      },
      location: {
        type: 'array',
        default: []
      },
      package: {
        type: 'array',
        default: []
      },
      category: {
        type: 'array',
        default: []
      },
      service: {
        type: 'array',
        default: []
      },
      employee: {
        type: 'array',
        default: []
      },
      parametars: {
        type: 'boolean',
        default: false
      },
      layout: {
        type: 'string',
        default: '1'
      },
      display_mode: {
        type: 'string',
        default: 'both'
      },
      default_button_initialized: {
        type: 'boolean',
        default: false
      },
      auto_trigger: {
        type: 'string',
        default: ''
      }
    },
    edit: function (props) {
      var inspectorElements = []
      var attributes = props.attributes
      var options = []
      var generatedAutoTrigger = 'amelia-step-booking-btn-' + props.clientId.replace(/[^a-zA-Z0-9]/g, '').slice(0, 8)
      var innerBlockCount = useSelect
        ? useSelect(function (select) {
          var block = select('core/block-editor').getBlock(props.clientId)
          return block && block.innerBlocks ? block.innerBlocks.length : 0
        }, [props.clientId])
        : 0

      // FIX: Move setAttributes calls out of render into useEffect to prevent
      // render-time side-effects that cause InnerBlocks template to re-apply
      // after the inner button is intentionally deleted by the user.

      // Keep the generated auto trigger unique per block instance.
      useEffect(function () {
        if (!attributes.trigger && attributes.auto_trigger !== generatedAutoTrigger) {
          props.setAttributes({
            auto_trigger: generatedAutoTrigger
          })
        }
      }, [attributes.trigger, attributes.auto_trigger, generatedAutoTrigger])

      // Initialize the default inner button exactly once per block instance.
      // New blocks get a starter button; once initialized, deleting that button
      // will not recreate it.
      useEffect(function () {
        if (!wp.data || !wp.blocks || attributes.default_button_initialized) {
          return
        }

        if (innerBlockCount > 0) {
          props.setAttributes({default_button_initialized: true})
          return
        }

        var defaultButtonsBlock = wp.blocks.createBlock(
          'core/buttons',
          {layout: {type: 'flex'}},
          [wp.blocks.createBlock('core/button', {text: getLabel('book_appointment', 'Book Appointment')})]
        )
        wp.data.dispatch('core/block-editor').replaceInnerBlocks(props.clientId, [defaultButtonsBlock], false)
        props.setAttributes({default_button_initialized: true})
      }, [attributes.default_button_initialized, innerBlockCount, props.clientId])

      // Backfill the initialization sentinel for existing blocks that already
      // contain content so deleting the button later doesn't recreate it.
      useEffect(function () {
        if (innerBlockCount > 0 && !attributes.default_button_initialized) {
          props.setAttributes({default_button_initialized: true})
        }
      }, [attributes.default_button_initialized, innerBlockCount])

      // Sync the short_code attribute whenever shortcode-relevant attributes change
      useEffect(function () {
        var newShortCode = computeShortCode(attributes)
        if (newShortCode !== attributes.short_code) {
          props.setAttributes({short_code: newShortCode})
        }
      }, [ // eslint-disable-line react-hooks/exhaustive-deps
        attributes.trigger,
        attributes.auto_trigger,
        attributes.trigger_type,
        attributes.in_dialog,
        attributes.parametars,
        attributes.show,
        attributes.service ? attributes.service.join(',') : '',
        attributes.category ? attributes.category.join(',') : '',
        attributes.employee ? attributes.employee.join(',') : '',
        attributes.location ? attributes.location.join(',') : '',
        attributes.package ? attributes.package.join(',') : '',
        attributes.layout
      ])

      options['categories'] = [{value: '', label: getLabel('show_all_categories', 'Show all categories')}]
      options['services'] = [{value: '', label: getLabel('show_all_services', 'Show all services')}]
      options['employees'] = [{value: '', label: getLabel('show_all_employees', 'Show all employees')}]
      options['locations'] = [{value: '', label: getLabel('show_all_locations', 'Show all locations')}]
      options['packages'] = [{value: '', label: getLabel('show_all_packages', 'Show all packages')}]
      options['show'] = [{value: '', label: getLabel('show_all', 'Show all')}, {value: 'services', label: getLabel('services', 'Services')}, {value: 'packages', label: getLabel('packages', 'Packages')}]
      options['trigger_type'] = [{value: 'id', label: getLabel('trigger_type_id', 'ID')}, {value: 'class', label: getLabel('trigger_type_class', 'Class')}]
      options['layout'] = [
        {value: '1', label: getLabel('layout_dropdown', 'Dropdown layout')},
        {value: '2', label: getLabel('layout_list', 'List layout')}
      ]

      function getOptions (data) {
        var options = []

        data = Object.keys(data).map(function (key) {
          return data[key]
        })

        data.sort(function (a, b) {
          if (parseInt(a.pos) < parseInt(b.pos)) return -1
          if (parseInt(a.pos) > parseInt(b.pos)) return 1
          return 0
        })

        data.forEach(function (element) {
          options.push({value: element.value, label: element.text})
        })

        return options
      }

      getOptions(categories)
        .forEach(function (element) {
          options['categories'].push(element)
        })

      getOptions(services)
        .forEach(function (element) {
          options['services'].push(element)
        })

      getOptions(employees)
        .forEach(function (element) {
          options['employees'].push(element)
        })

      if (locations.length) {
        getOptions(locations)
          .forEach(function (element) {
            options['locations'].push(element)
          })
      }

      if (packages.length) {
        getOptions(packages)
          .forEach(function (element) {
            options['packages'].push(element)
          })
      }

      // Renamed from getShortCode: no longer calls setAttributes during render.
      // short_code attribute is synced via the useEffect above instead.
      function computeShortCode (attributes) {
        var useAutoTrigger = !attributes.trigger
        var activeTrigger = attributes.trigger || (useAutoTrigger ? attributes.auto_trigger : '')
        var activeTriggerType = attributes.trigger ? attributes.trigger_type : 'id'
        var shortCode
        if (categories.length !== 0 && services.length !== 0 && employees.length !== 0) {
          if (attributes.parametars) {
            shortCode = '[ameliastepbooking'

            if (attributes.show) {
              shortCode += ' show=' + attributes.show + ''
            }

            if (attributes.service && attributes.service.length && !attributes.service.includes('')) {
              shortCode += ' service=' + attributes.service + ''
            } else if (attributes.category && attributes.category.length && !attributes.category.includes('')) {
              shortCode += ' category=' + attributes.category + ''
            }

            if (attributes.employee && attributes.employee.length && !attributes.employee.includes('')) {
              shortCode += ' employee=' + attributes.employee + ''
            }

            if (attributes.location && attributes.location.length && !attributes.location.includes('')) {
              shortCode += ' location=' + attributes.location + ''
            }

            if (attributes.package && attributes.package.length && !attributes.package.includes('')) {
              shortCode += ' package=' + attributes.package + ''
            }
          } else {
            shortCode = '[ameliastepbooking'
          }

          if (activeTrigger) {
            shortCode += ' trigger=' + activeTrigger + ''
          }

          if (activeTrigger && activeTriggerType) {
            shortCode += ' trigger_type=' + activeTriggerType + ''
          }

          if (activeTrigger && attributes.in_dialog) {
            shortCode += ' in_dialog=1'
          }

          // Add layout parameter to the shortcode
          if (attributes.layout) {
            shortCode += ' layout=' + attributes.layout + ''
          }

          shortCode += ']'
        } else {
          shortCode = 'Notice: Please create category, service and employee first.'
        }

        return shortCode
      }

      if (categories.length !== 0 && services.length !== 0 && employees.length !== 0) {

        inspectorElements.push(el(components.PanelRow,
          {},
          el('label', {htmlFor: 'amelia-js-parametars'}, getLabel('filter', 'Preselect Booking Parameters')),
          el(components.FormToggle, {
            id: 'amelia-js-parametars',
            checked: attributes.parametars,
            onChange: function () {
              return props.setAttributes({parametars: !props.attributes.parametars})
            }
          })
        ))

        inspectorElements.push(el('div', {style: {'margin-bottom': '1em'}}, ''))

        if (attributes.parametars) {
          inspectorElements.push(el('div', {class: 'amelia-gutenberg-multi-select-note'}, wpAmeliaLabels.multiselect_note))

          if (categories && categories.length > 1) {
            inspectorElements.push(el(components.SelectControl, {
              id: 'amelia-js-select-category',
              className: 'amelia-gutenberg-multi-select',
              label: wpAmeliaLabels.select_category,
              value: attributes.category,
              options: options.categories,
              multiple: true,
              onChange: function (selectControl) {
                return props.setAttributes({category: selectControl})
              }
            }))

            inspectorElements.push(el('div', {style: {'margin-bottom': '1em'}}, ''))
          }

          if (services && services.length > 1) {
            inspectorElements.push(el(components.SelectControl, {
              id: 'amelia-js-select-service',
              label: wpAmeliaLabels.select_service,
              className: 'amelia-gutenberg-multi-select',
              value: attributes.service,
              options: options.services,
              multiple: true,
              onChange: function (selectControl) {
                return props.setAttributes({service: selectControl})
              }
            }))

            inspectorElements.push(el('div', {style: {'margin-bottom': '1em'}}, ''))
          }

          if (employees && employees.length > 1) {
            inspectorElements.push(el(components.SelectControl, {
              id: 'amelia-js-select-employee',
              label: wpAmeliaLabels.select_employee,
              className: 'amelia-gutenberg-multi-select',
              value: attributes.employee,
              options: options.employees,
              multiple: true,
              onChange: function (selectControl) {
                return props.setAttributes({employee: selectControl})
              }
            }))

            inspectorElements.push(el('div', {style: {'margin-bottom': '1em'}}, ''))
          }

          if (locations && locations.length > 1) {
            inspectorElements.push(el(components.SelectControl, {
              id: 'amelia-js-select-location',
              className: 'amelia-gutenberg-multi-select',
              label: wpAmeliaLabels.select_location,
              value: attributes.location,
              options: options.locations,
              multiple: true,
              onChange: function (selectControl) {
                return props.setAttributes({location: selectControl})
              }
            }))

            inspectorElements.push(el('div', {style: {'margin-bottom': '1em'}}, ''))
          }

          if (packages.length) {
            inspectorElements.push(el(components.SelectControl, {
              id: 'amelia-js-select-package',
              label: wpAmeliaLabels.select_package,
              className: 'amelia-gutenberg-multi-select',
              value: attributes.package,
              options: options.packages,
              multiple: true,
              onChange: function (selectControl) {
                return props.setAttributes({package: selectControl})
              }
            }))

            inspectorElements.push(el('div', {style: {'margin-bottom': '1em'}}, ''))
          }

          if (packages.length) {
            inspectorElements.push(el('div', {style: {'margin-bottom': '1em'}}, ''))

            inspectorElements.push(el(components.SelectControl, {
              id: 'amelia-js-select-type',
              label: wpAmeliaLabels.show_all,
              value: attributes.show,
              options: options.show,
              onChange: function (selectControl) {
                return props.setAttributes({show: selectControl})
              }
            }))
          }
        }

        // Add Choose layout version dropdown
        inspectorElements.push(el('div', {style: {'margin-bottom': '1em'}}, ''))

        inspectorElements.push(el(components.SelectControl, {
          id: 'amelia-js-select-layout',
          label: wpAmeliaLabels.layout_select_label,
          value: attributes.layout,
          options: options.layout,
          onChange: function (selectControl) {
            return props.setAttributes({layout: selectControl})
          }
        }))

        var blockProps = useBlockProps()
        var triggerValue = attributes.trigger || attributes.auto_trigger
        var triggerType = attributes.trigger ? attributes.trigger_type : 'id'
        var triggerAttributes = {
          className: 'amelia-step-booking-button-trigger wp-block-buttons'
        }

        if (triggerValue) {
          if (triggerType === 'class') {
            triggerAttributes.className += ' ' + triggerValue
          } else {
            triggerAttributes.id = triggerValue
          }
        }

        return [
          el(blockControls, {key: 'controls'}),
          el(inspectorControls, {key: 'inspector'},
            el(components.PanelBody, {initialOpen: true},
              inspectorElements
            )
          ),
          el('div', blockProps,
            el('div', triggerAttributes,
              el(InnerBlocks, {
                allowedBlocks: ['core/buttons', 'core/button'],
                templateLock: false
              })
            ),
            el('div', {className: 'amelia-step-booking-shortcode', style: {display: 'none'}},
              computeShortCode(attributes)
            )
          )
        ]
      } else {
        inspectorElements.push(el('p', {style: {'margin-bottom': '1em'}}, 'Please create category, services and employee first. You can find instructions in our documentation on link below.'))
        inspectorElements.push(el('a', {href: 'https://wpamelia.com/quickstart/', target: '_blank', style: {'margin-bottom': '1em'}}, 'Start working with Amelia WordPress Appointment Booking plugin'))

        return [
          el(blockControls, {key: 'controls'}),
          el(inspectorControls, {key: 'inspector'},
            el(components.PanelBody, {initialOpen: true},
              inspectorElements
            )
          ),
          el('div',
            {style: blockStyle},
            computeShortCode(attributes)
          )
        ]
      }
    },

    save: function (props) {
      var blockProps = useBlockProps.save()
      var useAutoTrigger = !props.attributes.trigger
      var triggerValue = props.attributes.trigger || (useAutoTrigger ? props.attributes.auto_trigger : '')
      var triggerType = props.attributes.trigger ? props.attributes.trigger_type : 'id'
      var triggerAttributes = {
        className: 'amelia-step-booking-button-trigger wp-block-buttons'
      }

      if (triggerValue) {
        if (triggerType === 'class') {
          triggerAttributes.className += ' ' + triggerValue
        } else {
          triggerAttributes.id = triggerValue
        }
      }

      return (
        el('div', blockProps,
          el('div', triggerAttributes,
            el(InnerBlocks.Content)
          ),
          el('div', {className: 'amelia-step-booking-shortcode', style: {display: 'none'}},
            props.attributes.short_code
          )
        )
      )
    }
  })


  if (addFilter && withAmeliaStepBookingButtonControls) {
    addFilter(
      'editor.BlockEdit',
      'amelia/step-booking-button-inline-shortcode-controls',
      withAmeliaStepBookingButtonControls
    )
  }
})(
  window.wp
)
