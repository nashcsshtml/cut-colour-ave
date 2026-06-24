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
  var useRef = wp.element.useRef
  var wpAmeliaLabels = window.wpAmeliaLabels || {data: {}}
  var data = wpAmeliaLabels.data || {
    events: [],
    tags: [],
    locations: []
  }

  function getLabel (key, fallback) {
    return wpAmeliaLabels[key] || fallback
  }

  function getBlockLabel (groupKey, key, fallback) {
    return wpAmeliaLabels[groupKey] && wpAmeliaLabels[groupKey][key]
      ? wpAmeliaLabels[groupKey][key]
      : fallback
  }

  var entityNames = ['events', 'tags', 'locations']
  var entities = {}

  entityNames.forEach(function (entityName) {
    entities[entityName] = []

    if (!Array.isArray(data[entityName]) || data[entityName].length === 0) {
      return
    }

    for (var i = 0; i < data[entityName].length; i++) {
      if (!data[entityName][i]) {
        continue
      }

      entities[entityName].push({
        value: data[entityName][i].id,
        text: data[entityName][i].name + (entityName !== 'tags'
          ? ' (id: ' + data[entityName][i].id + ')' + (data[entityName][i].formattedPeriodStart ? (' - ' + data[entityName][i].formattedPeriodStart) : '')
          : '')
      })
    }
  })

  function getEntityOptions (entityName, defaultLabel) {
    var options = [{value: '', label: defaultLabel}]

    entities[entityName]
      .slice()
      .sort(function (a, b) {
        return parseInt(a.value, 10) - parseInt(b.value, 10)
      })
      .forEach(function (item) {
        options.push({value: item.value, label: item.text})
      })

    return options
  }

  function getShortcodeOptionsConfig () {
    return {
      events: getEntityOptions('events', getLabel('show_all_events', 'Show all events')),
      tags: getEntityOptions('tags', getLabel('show_all_tags', 'Show all tags')),
      locations: getEntityOptions('locations', getLabel('show_all_locations', 'Show all locations')),
      trigger_type: [
        {value: 'id', label: getLabel('trigger_type_id', 'ID')},
        {value: 'class', label: getLabel('trigger_type_class', 'Class')}
      ],
      event_to_show: [
        {value: 'all', label: getLabel('all_events', 'All events')},
        {value: 'future', label: getLabel('future_events', 'Future events')},
        {value: 'past', label: getLabel('past_events', 'Past events')},
        {value: 'custom', label: getLabel('custom_range', 'Custom range')}
      ]
    }
  }

  function hasSelectedValues (value) {
    if (!value) {
      return false
    }

    if (Array.isArray(value)) {
      return value.length > 0 && !value.includes('')
    }

    return value !== ''
  }

  function hasSpecificEventSelected (attributes) {
    return hasSelectedValues(attributes.event)
  }

  function formatTagAttribute (tags) {
    if (!Array.isArray(tags) || !tags.length) {
      return ''
    }

    return tags
      .filter(function (tag) {
        return !!tag
      })
      .map(function (tag) {
        return '{' + tag + '}'
      })
      .join(',')
  }

  function getCustomRangeValue (attributes) {
    var _d = new Date()
    var today = _d.getFullYear() + '-' + String(_d.getMonth() + 1).padStart(2, '0') + '-' + String(_d.getDate()).padStart(2, '0')
    var startDate = attributes.start_date || today
    var endDate = attributes.end_date || today

    return startDate + ' - ' + endDate
  }

  function computeShortCode (attributes) {
    var useAutoTrigger = !attributes.trigger
    var activeTrigger = attributes.trigger || (useAutoTrigger ? attributes.auto_trigger : '')
    var activeTriggerType = attributes.trigger ? attributes.trigger_type : 'id'
    var shortCode

    if (entities.events.length !== 0) {
      shortCode = '[ameliaeventslistbooking'

      if (attributes.parametars) {
        if (hasSpecificEventSelected(attributes)) {
          shortCode += ' event=' + attributes.event + ''

          if (attributes.recurring) {
            shortCode += ' recurring=1'
          }
        }

        if (!hasSpecificEventSelected(attributes) && attributes.event_to_show && attributes.event_to_show !== 'all') {
          shortCode += ' range="' + (attributes.event_to_show === 'custom'
            ? getCustomRangeValue(attributes)
            : attributes.event_to_show) + '"'
        }

        if (entities.tags.length && hasSelectedValues(attributes.tag)) {
          shortCode += ' tag="' + formatTagAttribute(attributes.tag) + '"'
        }

        if (entities.locations.length && hasSelectedValues(attributes.location)) {
          shortCode += ' location=' + attributes.location + ''
        }
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

      shortCode += ']'
    } else {
      shortCode = 'Notice: Please create event first.'
    }

    return shortCode
  }

  function renderShortcodeSettingsControls (attributes, setAttrs, options, startDatePickerRef, endDatePickerRef) {
    var controls = [
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
      el('div', {style: {'margin-bottom': '1em'}}, '')
    ]

    if (attributes.parametars) {
      controls.push(
        el('div', {className: 'amelia-gutenberg-multi-select-note'}, getLabel('multiselect_note', 'For multiselect: hold CTRL / Command (Cmd).'))
      )

      if (!hasSpecificEventSelected(attributes)) {
        controls.push(
          el(components.SelectControl, {
            id: 'amelia-js-select-event-to-show',
            label: getLabel('event_time_scope', 'Event Time Scope'),
            value: attributes.event_to_show,
            options: options.event_to_show,
            onChange: function (value) {
              setAttrs({event_to_show: value})
            }
          })
        )

        if (attributes.event_to_show === 'custom') {
          controls.push(
            el(components.BaseControl,
              {
                id: 'amelia-js-start-date',
                className: 'amelia-date-control',
                label: getLabel('red_start_date', 'Start Date')
              },
              el('div', {ref: startDatePickerRef},
                el(components.DatePicker, {
                  currentDate: attributes.start_date,
                  onChange: function (date) {
                    var formattedDate = date ? date.split('T')[0] : ''

                    setAttrs({
                      start_date: formattedDate,
                      end_date: !formattedDate
                        ? attributes.end_date
                        : (!attributes.end_date || attributes.end_date < formattedDate
                          ? formattedDate
                          : attributes.end_date)
                    })
                  },
                  is12Hour: false
                })
              )
            )
          )

          controls.push(
            el(components.BaseControl,
              {
                id: 'amelia-js-end-date',
                className: 'amelia-date-control',
                label: getLabel('red_end_date', 'End Date')
              },
              el('div', {ref: endDatePickerRef},
                el(components.DatePicker, {
                  currentDate: attributes.end_date,
                  onChange: function (date) {
                    var formattedDate = date ? date.split('T')[0] : ''

                    setAttrs({
                      start_date: !attributes.start_date && formattedDate
                        ? formattedDate
                        : attributes.start_date,
                      end_date: attributes.start_date && formattedDate && formattedDate < attributes.start_date
                        ? attributes.start_date
                        : formattedDate
                    })
                  },
                  is12Hour: false
                })
              )
            )
          )
        }
      }

      if (entities.tags.length) {
        controls.push(
          el(components.SelectControl, {
            id: 'amelia-js-select-tag',
            className: 'amelia-gutenberg-multi-select',
            label: getLabel('select_tag', 'Select tag'),
            value: attributes.tag,
            options: options.tags,
            multiple: true,
            onChange: function (value) {
              setAttrs({tag: value})
            }
          })
        )
      }

      if (entities.events.length) {
        controls.push(
          el(components.SelectControl, {
            id: 'amelia-js-select-event',
            className: 'amelia-gutenberg-multi-select',
            label: getLabel('select_event', 'Select event'),
            value: attributes.event,
            options: options.events,
            multiple: true,
            onChange: function (value) {
              setAttrs({event: value})
            }
          })
        )

        controls.push(el('div', {style: {'margin-bottom': '1em'}}, ''))

        controls.push(
          el(components.PanelRow,
            {},
            el('label', {htmlFor: 'amelia-js-recurring'}, getLabel('recurring_event', 'Recurring event')),
            el(components.FormToggle, {
              id: 'amelia-js-recurring',
              checked: attributes.recurring,
              onChange: function () {
                setAttrs({recurring: !attributes.recurring})
              }
            })
          )
        )
      }

      if (entities.locations.length) {
        controls.push(
          el(components.SelectControl, {
            id: 'amelia-js-select-location',
            className: 'amelia-gutenberg-multi-select',
            label: getLabel('select_location', 'Select location'),
            value: attributes.location,
            options: options.locations,
            multiple: true,
            onChange: function (value) {
              setAttrs({location: value})
            }
          })
        )
      }
    }

    return controls
  }

  var withAmeliaEventsListBookingButtonControls = createHigherOrderComponent
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
            if (block && block.name === 'amelia/events-list-booking-button-gutenberg-block') {
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
        var startDatePickerRef = {current: null}
        var endDatePickerRef = {current: null}

        function setAttrs (nextAttributes) {
          updateBlockAttributes(targetClientId, nextAttributes)
        }

        return el(Fragment, {},
          el(BlockEdit, props),
          el(inspectorControls, {},
            el(
              components.PanelBody,
              {title: getLabel('events_list_button_shortcode', 'Amelia Events List Booking Shortcode'), initialOpen: true},
              renderShortcodeSettingsControls(targetAttributes, setAttrs, options, startDatePickerRef, endDatePickerRef)
            )
          )
        )
      }
    }, 'withAmeliaEventsListBookingButtonControls')
    : null

  wp.blocks.registerBlockType('amelia/events-list-booking-button-gutenberg-block', {
    apiVersion: 3,
    title: getBlockLabel('events_list_booking_button_gutenberg_block', 'title', 'Amelia - Events List Button'),
    description: getBlockLabel('events_list_booking_button_gutenberg_block', 'description', 'Add an Amelia events list booking trigger button.'),
    icon: window.ameliaBlockIcon,
    category: 'amelia-blocks',
    keywords: [
      'amelia',
      'events'
    ],
    supports: {
      customClassName: false,
      html: false
    },
    attributes: {
      short_code: {
        type: 'string',
        default: '[ameliaeventslistbooking]'
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
      event: {
        type: 'array',
        default: []
      },
      recurring: {
        type: 'boolean',
        default: false
      },
      tag: {
        type: 'array',
        default: []
      },
      location: {
        type: 'array',
        default: []
      },
      parametars: {
        type: 'boolean',
        default: false
      },
      event_to_show: {
        type: 'string',
        default: 'all'
      },
      start_date: {
        type: 'string',
        default: ''
      },
      end_date: {
        type: 'string',
        default: ''
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
      var options = getShortcodeOptionsConfig()
      var startDatePickerRef = useRef(null)
      var endDatePickerRef = useRef(null)
      var generatedAutoTrigger = 'amelia-events-list-booking-btn-' + props.clientId.replace(/[^a-zA-Z0-9]/g, '').slice(0, 8)
      var innerBlockCount = useSelect
        ? useSelect(function (select) {
          var block = select('core/block-editor').getBlock(props.clientId)
          return block && block.innerBlocks ? block.innerBlocks.length : 0
        }, [props.clientId])
        : 0

      useEffect(function () {
        if (!attributes.trigger && attributes.auto_trigger !== generatedAutoTrigger) {
          props.setAttributes({
            auto_trigger: generatedAutoTrigger
          })
        }
      }, [attributes.trigger, attributes.auto_trigger, generatedAutoTrigger])

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
          [wp.blocks.createBlock('core/button', {text: getLabel('event_book_event', 'Book event')})]
        )
        wp.data.dispatch('core/block-editor').replaceInnerBlocks(props.clientId, [defaultButtonsBlock], false)
        props.setAttributes({default_button_initialized: true})
      }, [attributes.default_button_initialized, innerBlockCount, props.clientId])

      useEffect(function () {
        if (innerBlockCount > 0 && !attributes.default_button_initialized) {
          props.setAttributes({default_button_initialized: true})
        }
      }, [attributes.default_button_initialized, innerBlockCount])

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
        attributes.recurring,
        attributes.event_to_show,
        attributes.start_date,
        attributes.end_date,
        attributes.event ? attributes.event.join(',') : '',
        attributes.tag ? attributes.tag.join(',') : '',
        attributes.location ? attributes.location.join(',') : ''
      ])

      useEffect(function () {
        var hasCustomRangeControls = attributes.parametars &&
          attributes.event_to_show === 'custom' &&
          !hasSpecificEventSelected(attributes)

        if (!hasCustomRangeControls) {
          return undefined
        }

        function applyWeekdayLabelStyles (container) {
          if (!container) {
            return
          }

          var datePickerRoot = container.querySelector('.components-datetime__date')

          if (!datePickerRoot) {
            return
          }

          var calendar = Array.from(datePickerRoot.children)
            .find(function (child) {
              return child.querySelector('button.components-datetime__date__day')
            })

          if (!calendar) {
            return
          }

          Array.from(calendar.children)
            .filter(function (child) {
              return child.tagName === 'DIV'
            })
            .forEach(function (child) {
              child.style.maxWidth = '26px'
              child.style.overflow = 'hidden'
              child.style.textOverflow = 'ellipsis'
              child.style.whiteSpace = 'nowrap'
            })
        }

        function observeDatePicker (container) {
          if (!container) {
            return null
          }

          applyWeekdayLabelStyles(container)

          var observer = new MutationObserver(function () {
            applyWeekdayLabelStyles(container)
          })

          observer.observe(container, {
            childList: true,
            subtree: true
          })

          return observer
        }

        function setupDatePickerObservers () {
          var dateControls = [startDatePickerRef.current, endDatePickerRef.current].filter(Boolean)
          var observers = []

          dateControls.forEach(function (control) {
            var observer = observeDatePicker(control)
            if (observer) {
              observers.push(observer)
            }
          })

          return observers
        }

        var observers = setupDatePickerObservers()

        if (!observers || observers.length === 0) {
          var domObserver = new MutationObserver(function () {
            var dateControls = document.querySelectorAll('.amelia-date-control')
            if (dateControls.length > 0) {
              domObserver.disconnect()
              observers = setupDatePickerObservers()
            }
          })

          domObserver.observe(document.body, {
            childList: true,
            subtree: true
          })

          return function () {
            domObserver.disconnect()
            if (observers) {
              observers.forEach(function (observer) {
                observer.disconnect()
              })
            }
          }
        }

        return function () {
          if (observers) {
            observers.forEach(function (observer) {
              observer.disconnect()
            })
          }
        }
      }, [attributes.event_to_show, attributes.parametars, attributes.event])

      function setAttrs (nextAttributes) {
        props.setAttributes(nextAttributes)
      }

      if (entities.events.length !== 0) {
        inspectorElements = renderShortcodeSettingsControls(attributes, setAttrs, options, startDatePickerRef, endDatePickerRef)

        var blockProps = useBlockProps()
        var triggerValue = attributes.trigger || attributes.auto_trigger
        var triggerType = attributes.trigger ? attributes.trigger_type : 'id'
        var triggerAttributes = {
          className: 'amelia-events-list-booking-button-trigger wp-block-buttons'
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
            el('div', {className: 'amelia-events-list-booking-shortcode', style: {display: 'none'}},
              computeShortCode(attributes)
            )
          )
        ]
      }

      inspectorElements.push(el('p', {style: {'margin-bottom': '1em'}}, 'Please create event first. You can find instructions in our documentation on link below.'))
      inspectorElements.push(el('a', {href: 'https://wpamelia.com/documentation/service-quick-start/', target: '_blank', style: {'margin-bottom': '1em'}}, 'Start working with Amelia WordPress Appointment Booking plugin'))

      return [
        el(blockControls, {key: 'controls'}),
        el(inspectorControls, {key: 'inspector'},
          el(components.PanelBody, {initialOpen: true},
            inspectorElements
          )
        ),
        el('div', {style: {color: 'red'}},
          computeShortCode(attributes)
        )
      ]
    },

    save: function (props) {
      var blockProps = useBlockProps.save()
      var useAutoTrigger = !props.attributes.trigger
      var triggerValue = props.attributes.trigger || (useAutoTrigger ? props.attributes.auto_trigger : '')
      var triggerType = props.attributes.trigger ? props.attributes.trigger_type : 'id'
      var triggerAttributes = {
        className: 'amelia-events-list-booking-button-trigger wp-block-buttons'
      }

      if (triggerValue) {
        if (triggerType === 'class') {
          triggerAttributes.className += ' ' + triggerValue
        } else {
          triggerAttributes.id = triggerValue
        }
      }

      return el('div', blockProps,
        el('div', triggerAttributes,
          el(InnerBlocks.Content)
        ),
        el('div', {className: 'amelia-events-list-booking-shortcode', style: {display: 'none'}},
          props.attributes.short_code
        )
      )
    }
  })

  if (addFilter && withAmeliaEventsListBookingButtonControls) {
    addFilter(
      'editor.BlockEdit',
      'amelia/events-list-booking-button-inline-shortcode-controls',
      withAmeliaEventsListBookingButtonControls
    )
  }
})(
  window.wp
)

