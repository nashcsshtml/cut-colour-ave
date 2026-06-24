/* eslint-disable quotes */
(function (wp) {
  var el = wp.element.createElement

  // Shared Amelia block icon
  window.ameliaBlockIcon = el(
    "svg",
    {
      width: "20",
      height: "20",
      viewBox: "0 0 20 20",
      fill: "none",
      xmlns: "http://www.w3.org/2000/svg"
    },
    el("path", {
      d: "M11.0084 1.32972V7.10632C11.0084 7.58281 11.2628 8.02212 11.675 8.25862L16.6701 11.1222C17.5529 11.6284 18.6513 10.9892 18.6513 9.96993V4.22064C18.6513 3.74647 18.3991 3.30833 17.9893 3.07124L12.9942 0.179748C12.1114 -0.331029 11.0078 0.307587 11.0078 1.32914L11.0084 1.32972Z",
      fill: "#4A3BD6"
    }),
    el("path", {
      d: "M9.64395 1.32972V7.10632C9.64395 7.58281 9.3895 8.02212 8.97739 8.25862L3.98222 11.1222C3.09946 11.6284 2.00108 10.9892 2.00108 9.96993V4.22064C2.00108 3.74647 2.25321 3.30833 2.663 3.07124L7.65817 0.179748C8.54093 -0.331029 9.64453 0.307587 9.64453 1.32914L9.64395 1.32972Z",
      fill: "#7165DF"
    }),
    el("path", {
      d: "M9.5927 9.40127L4.56913 12.2811C3.68231 12.7896 3.67941 14.0709 4.56449 14.5828L9.58806 17.4906C9.99785 17.7277 10.5027 17.7277 10.9119 17.4906L15.9355 14.5828C16.8206 14.0709 16.8177 12.7896 15.9308 12.2811L10.9073 9.40127C10.4998 9.16767 10.0002 9.16767 9.5927 9.40127Z",
      fill: "#9E94F8"
    })
  )

  // Shared Gutenberg helpers (icon script loads first for all Amelia blocks).
  window.ameliaGutenbergShared = window.ameliaGutenbergShared || {}

  window.ameliaGutenbergShared.getSharedShortcodeDepricatedAttributes = function () {
    return {
      ivy: {type: 'string', default: ''},
      trigger: {type: 'string', default: ''},
      trigger_type: {type: 'string', default: 'id'},
      in_dialog: {type: 'boolean', default: false},
    }
  }

  window.ameliaGutenbergShared.getSharedShortcodeAttributes = function () {
    return {
      ivy: {
        type: 'string',
        default: ''
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
        default: false
      },
    }
  }

  window.ameliaGutenbergShared.setSharedShortcodeElements = function (inspectorElements, components, attributes, props, options, data) {
    inspectorElements.push(el(components.TextControl, {
      id: 'amelia-js-trigger',
      label: wpAmeliaLabels.manually_loading,
      value: attributes.trigger,
      help: wpAmeliaLabels.manually_loading_description,
      onChange: function (TextControl) {
        return props.setAttributes({trigger: TextControl})
      }
    }))

    if (attributes.trigger) {
      inspectorElements.push(el(components.SelectControl, {
        id: 'amelia-js-trigger_type',
        label: wpAmeliaLabels.trigger_type,
        value: attributes.trigger_type,
        options: options.trigger_type,
        help: wpAmeliaLabels.trigger_type_tooltip,
        onChange: function (selectControl) {
          return props.setAttributes({trigger_type: selectControl})
        }
      }))

      inspectorElements.push(el(components.PanelRow,
        {},
        el('label', {htmlFor: 'amelia-js-in-dialog'}, wpAmeliaLabels.in_dialog),
        el(components.FormToggle, {
          id: 'amelia-js-in-dialog',
          checked: attributes.in_dialog,
          onChange: function () {
            return props.setAttributes({in_dialog: !props.attributes.in_dialog})
          }
        })
      ))
    }
    
    if (!attributes.trigger && data.ivy && data.ivy.length) {
      inspectorElements.push(el(components.SelectControl, {
        id: "amelia-js-select-ivy",
        label: wpAmeliaLabels.ivy,
        help: wpAmeliaLabels.ivy_tooltip,
        value: String(attributes.ivy),
        options: data.ivy,
        onChange: function (selectControl) {
          return props.setAttributes({ivy: selectControl})
        }
      }))
    }
  }

  window.ameliaGutenbergShared.getSharedShortcodeString = function (attributes) {
    let shortCode = ''

    if (attributes.trigger) {
      shortCode += ' trigger=' + attributes.trigger + ''
    }

    if (attributes.trigger && attributes.trigger_type) {
      shortCode += ' trigger_type=' + attributes.trigger_type + ''
    }

    if (attributes.trigger && attributes.in_dialog) {
      shortCode += ' in_dialog=1'
    }

    if (attributes.trigger === '' && attributes.ivy) {
      shortCode += ' ivy=' + attributes.ivy + ''
    }

    return shortCode
  }
})(window.wp)
