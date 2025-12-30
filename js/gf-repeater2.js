// IMMEDIATE DEBUG - This logs as soon as the file is parsed (before DOM ready)
console.log('[Repeater2] ========================================');
console.log('[Repeater2] gf-repeater2.js loaded - Version 2.3.9');
console.log('[Repeater2] ========================================');

var gfRepeater_repeater2s = {};
var gfRepeater_submitted = false;
var gfRepeater_preserved_values = {}; // Stores values from renamed inputs to preserve during re-initialization
var gfRepeater_live_values = {}; // Stores values captured in real-time as user types
// Removed gfRepeater_repeater2s_is_set to allow re-initialization for multiple forms/caching

/*
    gfRepeater_captureRenamedInputValues()
        Captures all values from already-renamed repeater inputs before re-initialization.
        This prevents value loss when gform_post_render fires and the repeater JS re-initializes.
*/
function gfRepeater_captureRenamedInputValues() {
    console.log('[Repeater2] Capturing renamed input values...');
    var capturedCount = 0;

    jQuery('.gform_wrapper').each(function() {
        var formId = this.id.split('_')[2];
        if (!formId) return;

        if (!gfRepeater_preserved_values[formId]) {
            gfRepeater_preserved_values[formId] = {};
        }

        // Find all inputs with renamed pattern: input_{childId}-{repeaterId}-{iteration}
        jQuery(this).find(':input').each(function() {
            var inputName = jQuery(this).attr('name');
            if (!inputName) return;

            // Match pattern: input_{childId}-{repeaterId}-{iteration} or with [] suffix
            var match = inputName.match(/^(input_[\d_.]+)-(\d+)-(\d+)(\[\])?$/);
            if (match) {
                var value = gfRepeater_getInputValue(jQuery(this));

                // Only preserve non-empty values (don't overwrite with empty)
                if (value !== '' && value !== false) {
                    gfRepeater_preserved_values[formId][inputName] = value;
                    capturedCount++;
                }
            }
        });

        // Also merge in any live-captured values
        if (gfRepeater_live_values[formId]) {
            jQuery.each(gfRepeater_live_values[formId], function(inputName, value) {
                if (value !== '' && value !== false) {
                    gfRepeater_preserved_values[formId][inputName] = value;
                }
            });
        }
    });

    console.log('[Repeater2] Captured ' + capturedCount + ' values from DOM, preserved values:', gfRepeater_preserved_values);
}

/*
    gfRepeater_restorePreservedValues()
        Restores preserved values to renamed inputs after re-initialization.
*/
function gfRepeater_restorePreservedValues() {
    jQuery.each(gfRepeater_preserved_values, function(formId, inputs) {
        jQuery.each(inputs, function(inputName, value) {
            // Find the input by name
            var inputElement = jQuery('[name="' + inputName + '"]');
            if (inputElement.length && value !== '' && value !== false) {
                // Only restore if current value is empty (don't overwrite prePopulate values)
                var currentValue = gfRepeater_getInputValue(inputElement);
                if (currentValue === '' || currentValue === false) {
                    gfRepeater_setInputValue(inputElement, value);
                }
            }
        });
    });
}

/*
    gfRepeater_setupLiveCapture()
        Sets up event listeners to capture input values as user types.
        This ensures values are stored even before gform_post_render fires.
*/
function gfRepeater_setupLiveCapture(formId) {
    console.log('[Repeater2] Setting up live capture for form:', formId);

    // Use event delegation on the form to capture all repeater input changes
    jQuery('#gform_' + formId).off('change.repeater2 input.repeater2 blur.repeater2')
        .on('change.repeater2 input.repeater2 blur.repeater2', ':input', function() {
            var inputName = jQuery(this).attr('name');
            if (!inputName) return;

            // Only capture renamed inputs (input_{childId}-{repeaterId}-{iteration})
            var match = inputName.match(/^(input_[\d_.]+)-(\d+)-(\d+)(\[\])?$/);
            if (match) {
                var value = gfRepeater_getInputValue(jQuery(this));

                if (!gfRepeater_live_values[formId]) {
                    gfRepeater_live_values[formId] = {};
                }

                // Store the value (even if empty, to track which fields have been interacted with)
                if (value !== '' && value !== false) {
                    gfRepeater_live_values[formId][inputName] = value;
                    // Also update preserved values immediately
                    if (!gfRepeater_preserved_values[formId]) {
                        gfRepeater_preserved_values[formId] = {};
                    }
                    gfRepeater_preserved_values[formId][inputName] = value;
                    console.log('[Repeater2] Live captured:', inputName, '=', value);
                }
            }
        });
}

/*
    gfRepeater_storeValuesToHiddenField()
        Stores all captured values to a hidden field for form submission backup.
*/
function gfRepeater_storeValuesToHiddenField(formId) {
    var hiddenFieldId = 'gf_repeater2_preserved_' + formId;
    var hiddenField = jQuery('#' + hiddenFieldId);

    if (!hiddenField.length) {
        hiddenField = jQuery('<input type="hidden" id="' + hiddenFieldId + '" name="gf_repeater2_preserved_values" />');
        jQuery('#gform_' + formId).append(hiddenField);
    }

    var allValues = {};
    if (gfRepeater_preserved_values[formId]) {
        jQuery.extend(allValues, gfRepeater_preserved_values[formId]);
    }
    if (gfRepeater_live_values[formId]) {
        jQuery.extend(allValues, gfRepeater_live_values[formId]);
    }

    hiddenField.val(JSON.stringify(allValues));
}

/*
    gfRepeater_getRepeaters()
        Collects all repeater2 info and stores it inside of the global array "gfRepeater_repeater2s". - First phase of setup.
*/
function gfRepeater_getRepeaters() {
    var repeater2Data = jQuery('.gform_wrapper').each(function () {
        var repeater2s = {};
        var formId = this.id.split('_')[2];
        var form = jQuery(this).children('form').first();
        var repeater2Id = 0;

        var repeater2Found = 0;
        var repeater2ChildCount = 0;
        var repeater2ParemCount = 0;
        var parentSection = null;
        var repeater2Info = {};
        var repeater2Children = {};
        var repeater2ChildrenInputData = {};
        var capturedData = {};
        var dataElement;
        var startElement;

        // Remove ajax action from form because ajax enabled forms are not yet supported.
        // if (jQuery(form).attr('action') == '/ajax-test') { jQuery(form).removeAttr('action'); }
        jQuery(this).find('.gfield').each(function () {
            if (repeater2Found == 0) {
                if (jQuery(this).has('.ginput_container_repeater2').length) {
                    // Repeater Start

                    repeater2Id += 1;

                    startElement = jQuery(this);
                    dataElement = startElement.find('.gform_repeater2');

                    repeater2Info = jQuery(dataElement).val();
                    if (repeater2Info) {
                        try {
                            repeater2Info = JSON.parse(repeater2Info);
                        } catch (e) {
                            console.log('Error parsing repeater2Info:', e);
                            repeater2Info = null;
                        }
                    }

                    // Ensure repeater2Info has the required structure
                    if (!repeater2Info) {
                        repeater2Info = {
                            children: {},
                            start: 1,
                            min: 1,
                            max: null
                        };
                    }
                    if (!repeater2Info.children) {
                        repeater2Info.children = {};
                    }

                    if (jQuery.captures()) {
                        capturedData = jQuery.captures(dataElement.attr('name'));
                        if (capturedData) {
                            try {
                                capturedData = JSON.parse(capturedData);
                                if (repeater2Id == 1 && capturedData['formId'] == formId) {
                                    gfRepeater_submitted = true;
                                }
                            } catch (e) {
                                console.log('Error parsing capturedData:', e);
                                capturedData = null;
                            }
                        }
                    }

                    if (repeater2Id == 1) {
                        jQuery(form).capture();
                    }

                    repeater2Found = 1;
                }
            } else {
                if (jQuery(this).has('.ginput_container_repeater2').length) {
                    return false;
                }
                if (jQuery(this).has('.ginput_container_repeater2-end').length) {
                    // Repeater End

                    var repeater2Controllers = {};
                    var endElement = jQuery(this);
                    var addElement = endElement.find('.gf_repeater2_add');
                    var removeElement = endElement.find('.gf_repeater2_remove');


                    // Add data attributes for tracking
                    jQuery(addElement).attr('data-form-id', formId).attr('data-repeater2-id', repeater2Id);
                    jQuery(removeElement).attr('data-form-id', formId).attr('data-repeater2-id', repeater2Id);

                    // Use proper event listeners with closures to capture formId and repeater2Id
                    (function(fId, rId) {
                        jQuery(addElement).off('click keypress').on('click keypress', function(e) {
                            if (e.type === 'keypress' && e.which !== 13) return;
                            gfRepeater_repeatRepeater(fId, rId);
                        });
                        jQuery(removeElement).off('click keypress').on('click keypress', function(e) {
                            if (e.type === 'keypress' && e.which !== 13) return;
                            gfRepeater_unrepeatRepeater(fId, rId);
                        });
                    })(formId, repeater2Id);

                    repeater2Controllers = {
                        add: addElement,
                        remove: removeElement,
                        data: dataElement,
                        start: startElement,
                        end: endElement
                    };

                    var repeater2Settings = {};
                    var repeater2Start = Number(repeater2Info['start']);
                    var repeater2Min = Number(repeater2Info['min']);
                    var repeater2Max = Number(repeater2Info['max']);
                    if (!repeater2Start || (repeater2Max && repeater2Start > repeater2Max)) { repeater2Start = 1; }
                    if (!repeater2Min || (repeater2Max && repeater2Min > repeater2Max)) { repeater2Min = 1; }
                    if (!repeater2Max || (repeater2Min && repeater2Max && repeater2Min > repeater2Max)) { repeater2Max = null; }

                    repeater2Settings = {
                        start: repeater2Start,
                        min: repeater2Min,
                        max: repeater2Max
                    };

                    var repeater2data = {};
                    var repeater2TabIndex = Number(dataElement.attr('tabindex'));
                    var prevRepeatCount = null;
                    if (gfRepeater_submitted && capturedData) { prevRepeatCount = capturedData['repeatCount']; }

                    repeater2data = {
                        repeatCount: 1,
                        prevRepeatCount: prevRepeatCount,
                        childrenCount: repeater2ChildCount,
                        paremCount: repeater2ParemCount,
                        tabIndex: repeater2TabIndex,
                        inputData: repeater2ChildrenInputData
                    };

                    repeater2s[repeater2Id] = {
                        data: repeater2data,
                        settings: repeater2Settings,
                        controllers: repeater2Controllers,
                        children: repeater2Children
                    };

                    // Set back to defaults for the next repeater2
                    repeater2Found = 0;
                    repeater2ChildCount = 0;
                    repeater2ParemCount = 0;
                    parentSection = null;
                    repeater2Children = {};
                    repeater2ChildrenInputData = {};
                    repeater2ChildrenPrePopulate = {};
                    repeater2RequiredChildren = null;
                } else {
                    // Repeater Child

                    repeater2ChildCount += 1;
                    var childElement = jQuery(this);
                    var childLabel = jQuery(this).children('.gfield_label').text();
                    var childId = jQuery(this).attr('id');
                    var childIdNum = null;
                    if (childId) {
                        var idParts = childId.split('_');
                        if (idParts.length > 2) {
                            childIdNum = idParts[2];
                        }
                    }

                    if (!childIdNum) {
                        return;
                    }

                    var childInputs = {};
                    var childInputNames = [];
                    var childInputCount = 0;
                    var childRequired = false;
                    var childInfo = null;
                    if (repeater2Info && repeater2Info['children'] && repeater2Info['children'][childIdNum]) {
                        childInfo = repeater2Info['children'][childIdNum];
                    }
                    var childParentSection = parentSection;
                    var childType;
                    var inputMask;
                    var conditionalLogic;

                    if (!childInfo) {
                        return;
                    }

                    if (jQuery(this).has('.ginput_container').length) {
                        var childContainerClasses = jQuery(this).find('.ginput_container').attr('class').split(/\s+/);
                        var searchFor = 'ginput_container_';

                        jQuery.each(childContainerClasses, function (key, value) {
                            if (value.slice(0, searchFor.length) == searchFor) {
                                childType = value.slice(searchFor.length, value.length);
                            }
                        });
                    } else if (jQuery(this).hasClass('gform_hidden')) {
                        childType = 'hidden';
                    } else if (jQuery(this).hasClass('gsection')) {
                        childType = 'section';
                    }

                    if (childType == 'section') {
                        parentSection = repeater2ChildCount;
                        childParentSection = null;
                    }

                    if (childInfo['required']) { childRequired = true; }
                    if (childInfo['inputMask']) { inputMask = childInfo['inputMask']; }
                    if (childInfo['conditionalLogic']) {
                        conditionalLogic = childInfo['conditionalLogic'];
                        conditionalLogic['skip'] = [];
                    }

                    jQuery(this).find(':input').each(function () {
                        childInputCount += 1;
                        var inputElement = jQuery(this);
                        var inputId = jQuery(this).attr('id');
                        var inputName = jQuery(this).attr('name');
                        var inputName2;
                        var inputDefaultValue = gfRepeater_getInputValue(inputElement);
                        var inputPrePopulate = {};

                        if (inputName) {
                            if (jQuery.inArray(inputName, childInputNames) == -1) { childInputNames.push(inputName); }
                            if (inputName.slice(-2) == '[]') { inputName2 = inputName.slice(0, inputName.length - 2); } else { inputName2 = inputName; }

                            if (childInfo['prePopulate']) {
                                if (childType == 'checkbox' || childType == 'radio') {
                                    inputPrePopulate = childInfo['prePopulate'];
                                } else {
                                    // Check if this is a multi-input field (name, address, etc.) with sub-input data
                                    // Input name format: input_fieldId.subInputId (e.g., input_172.3)
                                    var fieldIdPart = inputName2.replace(/^input_/, ''); // Remove 'input_' prefix
                                    var subInputId = null;

                                    // Check for dot notation (e.g., "172.3" -> subInputId = "3")
                                    if (fieldIdPart.indexOf('.') !== -1) {
                                        subInputId = fieldIdPart.split('.')[1];
                                    }

                                    if (subInputId && childInfo['prePopulate'][subInputId]) {
                                        // Multi-input field: use sub-input specific prePopulate
                                        inputPrePopulate = childInfo['prePopulate'][subInputId];
                                    } else if (!subInputId) {
                                        // Simple single-input field: use entire prePopulate object (iteration-based)
                                        inputPrePopulate = childInfo['prePopulate'];
                                    } else {
                                        // Multi-input field but this sub-input has no prePopulate data
                                        inputPrePopulate = {};
                                    }
                                }

                                if (inputPrePopulate) {
                                    jQuery.each(inputPrePopulate, function (key, value) {
                                        if (key > repeater2ParemCount) { repeater2ParemCount = Number(key); }
                                    });
                                }
                            }
                        };

                        childInputs[childInputCount] = {
                            element: inputElement,
                            id: inputId,
                            name: inputName,
                            defaultValue: inputDefaultValue,
                            prePopulate: inputPrePopulate
                        };
                    });

                    repeater2Children[repeater2ChildCount] = {
                        element: childElement,
                        id: childId,
                        idNum: childIdNum,
                        inputs: childInputs,
                        inputCount: childInputCount,
                        required: childRequired,
                        type: childType,
                        inputMask: inputMask,
                        conditionalLogic: conditionalLogic,
                        parentSection: childParentSection
                    };


                    repeater2ChildrenInputData[childIdNum] = childInputNames;
                }
            }
        });

        if (repeater2Found !== 0) { return false; }

        if (repeater2s) {
            gfRepeater_repeater2s[formId] = repeater2s;
            return true;
        }
    });

    if (repeater2Data) { return true; } else { return false; }
}

/*
    gfRepeater_setRepeaterChildAttrs(formId, repeater2Id, repeater2ChildElement, repeatId)
        Adds the repeater2 ID number and Count number to the end of repeater2 child ID and name.

        formId					The form Id.
        repeater2Id				The repeater2 ID.
        repeater2ChildElement	The child element to run the function for.
        repeatId (Optional)		The repeatId to assign the child to. If a number is not specified, one will be automatically assigned. A 1 is required the first time this function is used during the setup process.
*/
function gfRepeater_setRepeaterChildAttrs(formId, repeater2Id, repeater2ChildElement, repeatId) {
    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];
    if (!repeatId) { var repeatId = repeater2['data']['repeatCount'] + 1; }
    var childId = jQuery(repeater2ChildElement).attr('id').split('-')[0];
    var childKey = gfRepeater_getIndex(repeater2['children'], 'id', childId);
    var checkValidation = jQuery('#gform_wrapper_' + formId).hasClass('gform_validation_error');

    if (childKey) {
        var failedValidation = false;
        var child = repeater2['children'][childKey];
        var childRequired = child['required'];
        var childType = child['type'];
        var inputCount = child['inputCount'];
        var inputMask = child['inputMask'];
        var tabindex = repeater2['data']['tabIndex'];

        var newRootId = childId + '-' + repeater2Id + '-' + repeatId;
        jQuery(repeater2ChildElement)
            .attr('id', newRootId)
            .attr('data-repeater2-parentId', repeater2Id)
            .attr('data-repeater2-repeatId', repeatId)
            .attr('data-repeater2-childId', childKey)
            .addClass('gf_repeater2_child_field');

        gfRepeater_replaceShortcodes(repeater2ChildElement);
        gfRepeater_doShortcode(repeater2ChildElement, 'count', repeatId);
        gfRepeater_doShortcode(repeater2ChildElement, 'buttons', repeater2['controllers']['add'].parent().clone());
        gfRepeater_doShortcode(repeater2ChildElement, 'add', repeater2['controllers']['add'].clone());
        gfRepeater_doShortcode(repeater2ChildElement, 'remove', repeater2['controllers']['remove'].clone());

        // Use proper event listeners with closures to capture formId, repeater2Id, and repeatId
        (function(fId, rId, repId) {
            var removeBtn = jQuery(repeater2ChildElement).find('.gf_repeater2_remove');

            // Add data attributes for tracking
            removeBtn.attr('data-form-id', fId)
                    .attr('data-repeater2-id', rId)
                    .attr('data-repeat-id', repId);

            removeBtn.off('click keypress')
                .on('click keypress', function(e) {
                    if (e.type === 'keypress' && e.which !== 13) return;
                    gfRepeater_unrepeatRepeater(fId, rId, repId);
                })
                .show();
        })(formId, repeater2Id, repeatId);

        jQuery(repeater2ChildElement)
            .find('.gf_repeater2_add')
            .show();

        jQuery.each(repeater2['children'][childKey]['inputs'], function (key, value) {
            var inputId = this['id'];
            var inputName = this['name'];
            var prePopulate = '';

            if (childType == 'radio') {
                var inputElement = gfRepeater_findElementByNameOrId(repeater2ChildElement, null, inputId);
            } else {
                var inputElement = gfRepeater_findElementByNameOrId(repeater2ChildElement, inputName, inputId);
            }

            inputElement.attr('data-repeater2-inputId', key);

            if (inputId) {
                var newInputId = inputId + '-' + repeater2Id + '-' + repeatId;
                jQuery(inputElement).attr('id', newInputId);
                jQuery(repeater2ChildElement).find("label[for^='" + inputId + "']").attr('for', newInputId);
            }

            if (inputName) {
                if (inputName.slice(-2) == '[]') {
                    var newInputName = inputName.slice(0, inputName.length - 2) + '-' + repeater2Id + '-' + repeatId + '[]';
                } else {
                    var newInputName = inputName + '-' + repeater2Id + '-' + repeatId;
                }

                jQuery(inputElement)
                    .attr('name', newInputName)
                    .attr('tabindex', tabindex);
            }

            // Maybe include https://www.geeksforgeeks.org/jquery-mask-plugin/
            if (inputMask) { jQuery(inputElement).mask(inputMask); }

            if (this['prePopulate'][repeatId]) {
                prePopulate = this['prePopulate'][repeatId];
            } else if (this['prePopulate'][0]) {
                prePopulate = this['prePopulate'][0];
            }

            if (prePopulate) {
                if (childType == 'checkbox' || childType == 'radio') {
                    // Handle both string format ("value1,value2") and object format ({iteration: value})
                    var prePopulateStr = '';
                    if (typeof prePopulate === 'string') {
                        prePopulateStr = prePopulate;
                    } else if (typeof prePopulate === 'object') {
                        // Object format - extract values and join with commas
                        var values = [];
                        jQuery.each(prePopulate, function(k, v) {
                            if (typeof v === 'string' && v !== '') {
                                values.push(v);
                            } else if (typeof v === 'object') {
                                // Nested object - extract inner values
                                jQuery.each(v, function(k2, v2) {
                                    if (v2 !== '') values.push(v2);
                                });
                            }
                        });
                        prePopulateStr = values.join(',');
                    }

                    prePopulateValues = prePopulateStr.split(',');
                    if (jQuery.inArray(key, prePopulateValues) !== -1) {
                        prePopulate = true;
                    } else {
                        prePopulate = false;
                    }
                }

                gfRepeater_setInputValue(inputElement, prePopulate);
            }

            if (window['gformInitDatepicker'] && childType == 'date' && inputCount == 2 && key == 1) {
                jQuery(inputElement)
                    .removeClass('hasDatepicker')
                    .datepicker('destroy')
                    .siblings('.ui-datepicker-trigger').remove();

                // Reinitialize datepicker for cloned elements
                setTimeout(function () {
                    if (window['gformInitDatepicker']) {
                        gformInitDatepicker();
                    }
                }, 100);
            }

            // Handle time fields
            if (window['gformInitTimepicker'] && childType == 'time') {
                // Reset time field and reinitialize
                setTimeout(function () {
                    if (window['gformInitTimepicker']) {
                        gformInitTimepicker();
                    }
                }, 100);
            }

            if (gfRepeater_submitted && checkValidation) {
                if (newInputName) {
                    var savedValue = jQuery.captures(newInputName);
                    if (savedValue) {
                        gfRepeater_setInputValue(inputElement, savedValue);
                    }
                }

                if (childRequired) {
                    if (newInputName) {
                        var splitName = newInputName.replace('.', '_').split(/(_|-)/);
                        if (childType == 'name' && jQuery.inArray(splitName[4], ['3', '6']) == -1) { return true; }
                        if (childType == 'address' && jQuery.inArray(splitName[4], ['2']) !== -1) { return true; }
                    }

                    var inputValue = gfRepeater_getInputValue(inputElement);
                    if (!inputValue && repeatId <= repeater2['data']['prevRepeatCount']) {
                        failedValidation = true;
                    }
                }
            }
        });

        if (childRequired) {
            var childLabel = repeater2ChildElement.children('.gfield_label');

            repeater2ChildElement.addClass('gfield_contains_required');

            if (!childLabel.has('.gfield_required').length) {
                childLabel.append("<span class=\"gfield_required\">*</span>");
            }

            if (gfRepeater_submitted && checkValidation) {
                if (failedValidation) {
                    repeater2ChildElement.addClass('gfield_error');
                    if (!repeater2ChildElement.has('.validation_message').length) {
                        repeater2ChildElement.append("<div class=\"gfield_description validation_message\">This field is required.</div>");
                    }
                } else {
                    repeater2ChildElement
                        .removeClass('gfield_error')
                        .find('.validation_message').remove();
                }
            }
        }
    }
}

/*
    gfRepeater_resetRepeaterChildrenAttrs(formId, repeater2Id)
        Resets all repeatId's so that they are chronological.
*/
function gfRepeater_resetRepeaterChildrenAttrs(formId, repeater2Id) {
    var repeater2Children = gfRepeater_select(formId, repeater2Id);
    var x = 0;

    jQuery(repeater2Children).each(function () {


        if (jQuery(this).attr('data-repeater2-childid') == 1) {
            x += 1;
        }

        if (jQuery(this).attr('data-repeater2-repeatid') !== x) {
            gfRepeater_setRepeaterChildAttrs(formId, repeater2Id, jQuery(this), x);

        }
    });
}

/*
    gfRepeater_conditionalLogic_set(formId, repeater2Id, repeater2ChildId, repeatId)
        Runs 'gfRepeater_conditionalLogic_do' and assigns change event for all fields involed with the repeater2ChildElement's conditional logic.
*/
function gfRepeater_conditionalLogic_set(formId, repeater2Id, repeater2ChildId, repeatId) {
    gfRepeater_conditionalLogic_do(formId, repeater2Id, repeater2ChildId, repeatId);

    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];
    var repeater2Child = repeater2['children'][repeater2ChildId]
    var conditionalLogic = repeater2Child['conditionalLogic'];

    jQuery.each(conditionalLogic['rules'], function (key, value) {
        var fieldId = value['fieldId'];
        var childId = gfRepeater_getIndex(repeater2['children'], 'idNum', fieldId);

        if (childId !== false) {
            var inputs = gfRepeater_select(formId, repeater2Id, repeatId, childId, '*');
        } else {
            var inputs = jQuery('#field_' + formId + '_' + fieldId + ' :input');
            repeatId = null;
        }

        jQuery.each(inputs, function (key, input) {
            jQuery(this).bind('propertychange change click keyup input paste', function () {
                gfRepeater_conditionalLogic_do(formId, repeater2Id, repeater2ChildId, repeatId);
            });
        });
    });
}

/*
    gfRepeater_conditionalLogic_setAll(formId, repeater2Id, repeatId)
        Sets conditionalLogic for all children inside of a repeatId.
*/
function gfRepeater_conditionalLogic_setAll(formId, repeater2Id, repeatId) {
    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];
    jQuery.each(repeater2['children'], function (key, value) {
        if (this.conditionalLogic) {
            gfRepeater_conditionalLogic_set(formId, repeater2Id, key, repeatId);
        }
    });
}

/*
    gfRepeater_conditionalLogic_do(formId, repeater2Id, repeater2ChildId, repeatId)
        Hides or Shows repeater2ChildElement depending on conditional logic.
*/
function gfRepeater_conditionalLogic_do(formId, repeater2Id, repeater2ChildId, repeatId) {
    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];
    var repeater2Child = repeater2['children'][repeater2ChildId]
    var conditionalLogic = repeater2Child['conditionalLogic'];
    var effectedIds = [repeater2ChildId];
    var conditions = [];
    var conditionsPassed = false;
    var hideField = false;

    jQuery.each(conditionalLogic['rules'], function (key, value) {
        var condition = false;
        var fieldId = value['fieldId'];
        var childId = gfRepeater_getIndex(repeater2['children'], 'idNum', fieldId);

        if (childId !== false) {
            var child = repeater2['children'][childId];
            var childElement = gfRepeater_select(formId, repeater2Id, repeatId, childId);

            if (child['type'] == 'checkbox' || child['type'] == 'radio') {
                var inputValue = gfRepeater_getChoiceValue(childElement);
                var multiInput = true;
            } else {
                var inputElement = gfRepeater_select(formId, repeater2Id, repeatId, childId, 1);
                var inputValue = gfRepeater_getInputValue(inputElement);
                var multiInput = false;
            }
        } else {
            var fieldElement = jQuery('#field_' + formId + '_' + fieldId);
            var firstInput = fieldElement.find(':input').first();

            if (firstInput.is(':checkbox, :radio')) {
                var inputValue = gfRepeater_getChoiceValue(fieldElement);
                var multiInput = true;
            } else {
                var inputValue = gfRepeater_getInputValue(firstInput);
                var multiInput = false;
            }
        }

        if (multiInput) {
            if (jQuery.inArray(value['value'], inputValue) !== -1) { inputValue = value['value']; } else { inputValue = false; }
        }

        condition = gf_matches_operation(inputValue, value['value'], value['operator']);
        conditions.push(condition);
    });

    if (conditionalLogic['logicType'] == 'all') {
        if (jQuery.inArray(false, conditions) == -1) { conditionsPassed = true; }
    } else {
        if (jQuery.inArray(true, conditions) !== -1) { conditionsPassed = true; }
    }

    if ((conditionsPassed && conditionalLogic['actionType'] !== 'show') || (!conditionsPassed && conditionalLogic['actionType'] == 'show')) {
        hideField = true;
    }

    if (repeater2Child['type'] == 'section') {
        var sectionChildren = gfRepeater_getIndex(repeater2['children'], 'parentSection', repeater2ChildId, true);
        if (sectionChildren !== false) { effectedIds = effectedIds.concat(sectionChildren); }
    }

    jQuery.each(effectedIds, function (key, value) {
        var effectedChild = repeater2['children'][value];
        var effectedLogic = effectedChild['conditionalLogic'];
        var effectedElement = gfRepeater_select(formId, repeater2Id, repeatId, value);
        var skipId = repeatId;

        if (skipId == null) { skipId = 'all'; }

        if (effectedElement.length) {
            if (hideField) {
                effectedElement.hide();

                if (effectedLogic) {
                    if (jQuery.inArray(skipId, effectedLogic['skip']) == -1) {
                        effectedLogic['skip'].push(skipId);
                    }
                }
            } else {
                effectedElement.show();

                if (effectedLogic) {
                    if (jQuery.inArray(skipId, effectedLogic['skip']) !== -1) {
                        var skipIndex = effectedLogic['skip'].indexOf(skipId);
                        effectedLogic['skip'].splice(skipIndex, 1);
                    }
                }
            }
        }
    });

    gfRepeater_updateDataElement(formId, repeater2Id);
}

/*
    gfRepeater_doShortcode(element, shortcode, value)
        Finds the 'shortcode' inside of 'element' and replaces it's contents with 'value'.

        element			The element to search inside.
        shortcode		The shortcode to search for.
        value			The value to put inside the shortcode.
*/
function gfRepeater_doShortcode(element, shortcode, value) {
    element.find('.gfRepeater-shortcode-' + shortcode).each(function () {
        jQuery(this).html(value);
    });
}

/*
    gfRepeater_replaceShortcodes(element)
        Replaces any repeater2 shortcodes with spans for those shortcodes.

        element			The element to search and replace.
*/
function gfRepeater_replaceShortcodes(element) {
    var shortcodes = ['count', 'buttons', 'add', 'remove'];

    jQuery.each(shortcodes, function (key, shortcode) {
        var html = element.html();
        element.html(html.replace('[gfRepeater-' + shortcode + ']', '<span class=\"gfRepeater-shortcode-' + shortcode + '\"></span>'));
    });
}

/*
    gfRepeater_repeatRepeater(formId, repeater2Id)
        Repeats the repeater2 once.

        formId				The form Id.
        repeater2Id			The repeater2 ID number to repeat.
*/
function gfRepeater_repeatRepeater(formId, repeater2Id) {

    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];

    if (!repeater2) {
        return;
    }

    var repeatId = repeater2['data']['repeatCount'] + 1;

    if (repeater2['settings']['max'] && repeater2['data']['repeatCount'] >= repeater2['settings']['max']) {
        return;
    }

    jQuery(repeater2['controllers']['start'])
        .parents('form')
        .trigger('gform_repeater2_before_repeat', [repeater2Id, repeatId]);

    var lastElement = gfRepeater_select(formId, repeater2Id).last();

    jQuery.each(repeater2['children'], function (key, value) {
        var clonedElement = jQuery(this.element).clone();

        gfRepeater_resetInputs(formId, repeater2Id, key, clonedElement);
        gfRepeater_setRepeaterChildAttrs(formId, repeater2Id, clonedElement);

        clonedElement.insertAfter(lastElement);
        lastElement.find('.datepicker').removeClass('initialized');
        lastElement = clonedElement;

        // Handle file upload fields in cloned elements
        clonedElement.find('input[type="file"]').each(function () {
            var fileInput = jQuery(this);
            // Reset file input value and clear any existing files
            fileInput.val('');
            // Clear any file preview elements
            fileInput.siblings('.ginput_preview').remove();
            fileInput.siblings('.gform_fileupload_rules').remove();
            // Reinitialize file input if needed
            if (window['gformInitFileUpload']) {
                gformInitFileUpload(fileInput);
            }
        });

        // Handle time fields in cloned elements
        clonedElement.find('.gfield_time').each(function () {
            var timeField = jQuery(this);
            // Reset time field values
            timeField.find('input').val('');
            // Reinitialize time field if needed
            if (window['gformInitTimepicker']) {
                gformInitTimepicker();
            }
        });
    });

    gfRepeater_conditionalLogic_setAll(formId, repeater2Id, repeatId);

    repeater2['data']['repeatCount'] += 1;
    gfRepeater_updateDataElement(formId, repeater2Id);
    gfRepeater_updateRepeaterControls(formId, repeater2Id);

    if (window['gformInitDatepicker']) { gformInitDatepicker(); }

    // Reinitialize other Gravity Forms field types for cloned elements
    if (window['gformInitFileUpload']) { gformInitFileUpload(); }
    if (window['gformInitChosenFields']) { gformInitChosenFields(); }
    if (window['gformInitTimepicker']) { gformInitTimepicker(); }

    jQuery(repeater2['controllers']['start'])
        .parents('form')
        .trigger('gform_repeater2_after_repeat', [repeater2Id, repeatId]);

}

/*
    gfRepeater_unrepeatRepeater(formId, repeater2Id, repeatId)
        Un-repeats the repeater2 once.

        formId						The form Id.
        repeater2Id					The repeater2 ID number to unrepeat.
        repeatId (Optional)			The repeat ID number to unrepeat. If an ID number is not specified, the last one will be chosen.
*/
function gfRepeater_unrepeatRepeater(formId, repeater2Id, repeatId) {

    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];

    if (!repeater2) {
        return;
    }

    if (repeater2['data']['repeatCount'] <= repeater2['settings']['min']) {
        return;
    }
    if (!repeatId) { var repeatId = repeater2['data']['repeatCount']; }

    jQuery(repeater2['controllers']['start'])
        .parents('form')
        .trigger('gform_repeater2_before_unrepeat', [repeater2Id, repeatId]);

    jQuery.each(repeater2['children'], function (childId, value) {
        gfRepeater_select(formId, repeater2Id, repeatId, childId).remove();
    });

    repeater2['data']['repeatCount'] -= 1;
    gfRepeater_updateDataElement(formId, repeater2Id);
    gfRepeater_updateRepeaterControls(formId, repeater2Id);

    if (repeatId !== repeater2['data']['repeatCount'] + 1) {
        gfRepeater_resetRepeaterChildrenAttrs(formId, repeater2Id);
    }

    jQuery(repeater2['controllers']['start'])
        .parents('form')
        .trigger('gform_repeater2_after_unrepeat', [repeater2Id, repeatId]);
}

/*
    gfRepeater_repeatRepeaterTimes(formId, repeater2Id, timesX)
        Repeats the repeater2 a multiple number of times depeneding on the 'timesX' variable.

        formId				The form Id.
        repeater2Id			The repeater2 ID number to repeat.
        timesX (Optional)	The number of times to repeat the repeater2. Default is 1.
*/
function gfRepeater_repeatRepeaterTimes(formId, repeater2Id, timesX) {
    if (!timesX) { var timesX = 1; }
    for (i = 0; i < timesX; i++) {
        gfRepeater_repeatRepeater(formId, repeater2Id);
    }
}

/*
    gfRepeater_unrepeatRepeaterTimes(formId, repeater2Id, timesX)
        UnRepeats the repeater2 a multiple number of times depeneding on the 'timesX' variable.

        formId				The form Id.
        repeater2Id			The repeater2 ID number to unrepeat.
        timesX (Optional)	The number of times to unrepeat the repeater2. Default is 1.
*/
function gfRepeater_unrepeatRepeaterTimes(formId, repeater2Id, timesX) {
    if (!timesX) { var timesX = 1; }
    for (i = 0; i < timesX; i++) {
        gfRepeater_unrepeatRepeater(formId, repeater2Id);
    }
}

/*
    gfRepeater_setRepeater(formId, repeater2Id, timesX)
        Repeats or unrepeats the repeater2 to set it to timesX.

        formId			The form Id.
        repeater2Id		The repeater2 ID number to repeat or unrepeat.
        timesX			The number to set the repeater2 to.
*/
function gfRepeater_setRepeater(formId, repeater2Id, timesX) {
    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];
    var currentRepeatCount = repeater2['data']['repeatCount'];

    if (timesX == currentRepeatCount) {
        return;
    } else if (timesX > currentRepeatCount) {
        var timesY = timesX - currentRepeatCount;
        gfRepeater_repeatRepeaterTimes(formId, repeater2Id, timesY);
    } else if (timesX < currentRepeatCount) {
        var timesY = currentRepeatCount - timesX;
        gfRepeater_unrepeatRepeaterTimes(formId, repeater2Id, timesY);
    }
}

/*
    gfRepeater_updateRepeaterControls(formId, repeater2Id)
        Updates the add and remove buttons for the repeater2. If the minimum repeat number has been reached, the remove button is hidden. If the maximum number has been reached, the add button is hidden.

        formId			The form Id.
        repeater2Id		The repeater2 ID number to update the controls for.
*/
function gfRepeater_updateRepeaterControls(formId, repeater2Id) {
    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];

    if (repeater2['settings']['max']) {
        if (repeater2['data']['repeatCount'] >= repeater2['settings']['max']) {
            jQuery(repeater2['controllers']['add']).hide();
        } else {
            jQuery(repeater2['controllers']['add']).show();
        }
    }

    if (repeater2['data']['repeatCount'] <= repeater2['settings']['min']) {
        jQuery(repeater2['controllers']['remove']).hide();
    } else {
        jQuery(repeater2['controllers']['remove']).show();
    }
}

/*
    gfRepeater_resetInputs(formId, repeater2Id, childId, repeater2ChildElement)
        Resets all input elements inside of a repeater2 child.

        formId					The form Id.
        repeater2Id				The repeater2 ID.
        childId					The repeater2 child ID number.
        repeater2ChildElement	The repeater2 child element.
*/
function gfRepeater_resetInputs(formId, repeater2Id, childId, repeater2ChildElement) {
    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];
    jQuery.each(repeater2['children'][childId]['inputs'], function (key, value) {
        var inputId = this['id'];
        var inputName = this['name'];
        var inputDefaultValue = this['defaultValue'];
        var inputElement = gfRepeater_findElementByNameOrId(repeater2ChildElement, inputName, inputId);

        if (inputElement) {
            gfRepeater_setInputValue(inputElement, inputDefaultValue);
        }
    });
}

/*
    gfRepeater_select(formId, repeater2Id, repeatId, childId, inputId)
        Selects an element depending on the variables passed.

        formId						The form Id.
        repeater2Id (Optional)		The repeater2 Id.
        repeatId (Optional)			The repeat Id.
        childId (Optional)			The child Id.
        inputId (Optional)			The input Id. Also accepts '*' to select all inputs.
*/
function gfRepeater_select(formId, repeater2Id, repeatId, childId, inputId) {
    var selector = 'div#gform_wrapper_' + formId + '>form#gform_' + formId;
    if (repeater2Id || repeatId || childId || inputId) { selector += '>.gform_body .gform_fields>.gfield.gf_repeater2_child_field'; }
    if (repeater2Id) { selector += '[data-repeater2-parentid=' + repeater2Id + ']'; }
    if (repeatId) { selector += '[data-repeater2-repeatid=' + repeatId + ']'; }
    if (childId) { selector += '[data-repeater2-childid=' + childId + ']'; }
    if (inputId) {
        if (inputId == '*') {
            selector += ' [data-repeater2-inputid]';
        } else {
            selector += ' [data-repeater2-inputid=' + inputId + ']';
        }
    }
    return jQuery(selector);
}

/*
    gfRepeater_findElementByNameOrId(searchElement, elementName, elementId)
        Searches for an an element inside of another element by ID or Name. If both an ID and a Name are supplied it will first try the Name and then the ID.

        searchElement			Element to search inside.
        inputName (Optional)	A element name to search for.
        inputId (Optional)		A element ID to search for.
*/
function gfRepeater_findElementByNameOrId(searchElement, elementName, elementId) {
    if (elementName) { var foundElement = jQuery(searchElement).find("[name^='" + elementName + "']"); }
    if (!foundElement && elementId) { var foundElement = jQuery(searchElement).find("[id^='" + elementId + "']"); }
    if (foundElement) { return foundElement; } else { return false; }
}

/*
    gfRepeater_getIndex
        Searches 'object' where 'key' equals 'value'.
        Returns first result if multiple is false.
        Returns array with all key results if multiple is true.
        Returns false if nothing was found.

        object		Object or array to search through.
        key			Key to search for.
        value		Value to search for.
        multiple	Set to true to return all results in an array.
*/
function gfRepeater_getIndex(object, key, value, multiple) {
    var keys = [];

    jQuery.each(object, function (fieldKey, fieldValue) {
        if (fieldValue[key] == value) {
            keys.push(fieldKey);
            if (!multiple) { return false; }
        }
    });

    if (keys.length) {
        if (multiple) {
            return keys;
        } else { return keys[0]; }
    } else { return false; }
}

/*
    gfRepeater_getChoiceValue(fieldElement)
        Searches 'fieldElement' for checkboxes and radios. Returns an array with the labels of all the values that are 'checked'.

        fieldElement	The element to search in.
*/
function gfRepeater_getChoiceValue(fieldElement) {
    var value = [];
    jQuery(fieldElement).find(':checkbox, :radio').each(function () {
        if (jQuery(this).prop('checked') == true) {
            var id = this.id;
            var label = jQuery(this).siblings('label').first().text();
            value.push(label);
        }
    });
    return value;
}

/*
    gfRepeater_getInputValue(inputElement)
        Gets the value of an input.

        inputElement	The input element.
*/
function gfRepeater_getInputValue(inputElement) {
    if (inputElement.is(':checkbox, :radio')) {
        if (inputElement.prop('checked') == true) { return true; } else { return false; }
    } else {
        return inputElement.val();
    }
}

/*
    gfRepeater_setInputValue(inputElement, value)
        Sets the value of an input.

        inputElement	The input element.
        inputValue		The value to set to the input.
*/
function gfRepeater_setInputValue(inputElement, inputValue) {
    if (inputElement.is(':checkbox, :radio')) {
        if (inputValue == 'on' || inputElement.prop('value') === inputValue) { inputElement.prop('checked', true) } else { inputElement.prop('checked', false) }
    } else {
        inputElement.val(inputValue);
    }
}

/*
    gfRepeater_updateDataElement(formId, repeater2Id)
        Updates the data element for the repater. The data element stores information that is passed to PHP for processing.

        formId			The form Id.
        repeater2Id		The repeater2 ID number to update the data element for.
*/
function gfRepeater_updateDataElement(formId, repeater2Id) {
    var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];
    var dataElement = jQuery(repeater2['controllers']['data']);

    var dataArray = jQuery(dataElement).val();
    if (dataArray) {
        try {
            dataArray = JSON.parse(dataArray);
        } catch (e) {
            console.log('Error parsing dataArray in updateDataElement:', e);
            dataArray = null;
        }
    }

    if (!dataArray) {
        dataArray = {
            repeater2Id: repeater2Id,
            repeatCount: 1,
            children: {}
        };
    }

    dataArray['repeater2Id'] = repeater2Id;
    dataArray['repeatCount'] = repeater2['data']['repeatCount'];

    if (dataArray['children']) {
        jQuery.each(dataArray['children'], function (key, value) {
            if (Array.isArray(this)) { dataArray['children'][key] = {}; }
            var inputData = repeater2['data']['inputData'][key];
            if (inputData && inputData.length) {
                dataArray['children'][key]['inputs'] = inputData;
            }
            var fieldIndex = gfRepeater_getIndex(repeater2['children'], 'idNum', key);
            // TODO: Temporarily comment this line out
            //dataArray['children'][key]['conditionalLogic'] = repeater2['children'][fieldIndex]['conditionalLogic'];
        });
    }

    dataArray = JSON.stringify(dataArray);
    jQuery(dataElement).val(dataArray);
}

/*
    gfRepeater_start()
        Runs the gfRepeater_setRepeaterChildAttrs function for the first set of repeater2 children and then repeats the repeater2 a number of times depending on the repeater2 setting. - Second phase of setup.
*/
function gfRepeater_start() {
    jQuery.each(gfRepeater_repeater2s, function (key, repeater2) {
        var formId = key;
        var form = gfRepeater_select(formId);

        jQuery.each(repeater2, function (key, value) {
            var repeater2Id = key;
            var repeater2 = gfRepeater_repeater2s[formId][repeater2Id];
            var repeatCount = repeater2['settings']['start'];
            var paremCount = repeater2['data']['paremCount'];

            if (repeater2['controllers']['data'].attr('data-required')) { repeater2['controllers']['start'].addClass('gfield_contains_required'); }

            jQuery.each(repeater2['children'], function (key, value) {

                gfRepeater_setRepeaterChildAttrs(formId, repeater2Id, jQuery(repeater2['children'][key]['element']), 1);
                if (this.conditionalLogic) { gfRepeater_conditionalLogic_set(formId, repeater2Id, key, 1); }
            });

            if (gfRepeater_submitted) {
                repeatCount = repeater2['data']['prevRepeatCount'];
            } else if (paremCount > repeatCount) {
                repeatCount = paremCount;
            }

            gfRepeater_setRepeater(formId, repeater2Id, repeatCount);
            gfRepeater_updateRepeaterControls(formId, repeater2Id);
            gfRepeater_updateDataElement(formId, repeater2Id);
        });

        // Set up live value capture for this form
        gfRepeater_setupLiveCapture(formId);

        jQuery(form).trigger('gform_repeater2_init_done');
    });

    if (window['gformInitDatepicker']) { gformInitDatepicker(); }
    if (window['gformInitTimepicker']) { gformInitTimepicker(); }
}

// Initiation after gravity forms has rendered.
// NOTE: Removed the gfRepeater_repeater2s_is_set flag to allow re-initialization for multiple forms
// and Gravity Forms caching/re-rendering. Event handlers use .off() to prevent duplicates.
jQuery(document).bind('gform_post_render', function (event, formId) {
    // IMPORTANT: Capture values from already-renamed inputs BEFORE re-initialization
    // This prevents value loss when gform_post_render fires multiple times
    gfRepeater_captureRenamedInputValues();

    if (gfRepeater_getRepeaters()) {
        gfRepeater_start();

        // Restore any preserved values that were lost during re-initialization
        gfRepeater_restorePreservedValues();

        // Also store values to hidden field as backup
        jQuery('.gform_wrapper').each(function() {
            var fId = this.id.split('_')[2];
            if (fId) {
                gfRepeater_storeValuesToHiddenField(fId);
            }
        });

        jQuery(window).trigger('gform_repeater2_init_done');
    } else {
        console.log('There was an error with one of your repeater2s. This is usually caused by forgetting to include a repeater2-end field or by trying to nest repeater2s.');
    }
});

// Hook into form submission to ensure values are preserved
// This is a safeguard in case gform_post_render fires during submission
jQuery(document).on('submit', 'form[id^="gform_"]', function(e) {
    var formId = jQuery(this).attr('id').replace('gform_', '');
    console.log('[Repeater2] Form submit event for form:', formId);
    console.log('[Repeater2] Live values at submission:', gfRepeater_live_values);
    console.log('[Repeater2] Preserved values at submission:', gfRepeater_preserved_values);

    // Capture any remaining values
    gfRepeater_captureRenamedInputValues();

    // Restore preserved values right before submission
    gfRepeater_restorePreservedValues();

    // Store to hidden field as final backup
    gfRepeater_storeValuesToHiddenField(formId);

    // Log the hidden field value
    var hiddenField = jQuery('#gf_repeater2_preserved_' + formId);
    if (hiddenField.length) {
        console.log('[Repeater2] Hidden field value:', hiddenField.val());
    }
});

// Also hook into Gravity Forms pre-submission event
jQuery(document).bind('gform_pre_submission', function(event, formId) {
    // Capture and restore values before GF processes the submission
    gfRepeater_captureRenamedInputValues();
    gfRepeater_restorePreservedValues();

    // Store to hidden field as final backup
    gfRepeater_storeValuesToHiddenField(formId);
});

// Hook into page change events to capture values when navigating between pages
jQuery(document).bind('gform_page_loaded', function(event, formId, currentPage) {
    console.log('[Repeater2] gform_page_loaded fired for form:', formId, 'page:', currentPage);

    // When a new page is loaded, restore any preserved values
    gfRepeater_restorePreservedValues();

    // Set up live capture for the new page
    gfRepeater_setupLiveCapture(formId);
});

// Hook into page change BEFORE it happens to capture current values
jQuery(document).on('click', '.gform_next_button, .gform_previous_button, .gform_button[type="submit"]', function(e) {
    var form = jQuery(this).closest('form');
    var formId = form.attr('id') ? form.attr('id').replace('gform_', '') : null;

    console.log('[Repeater2] Navigation/submit button clicked, capturing values for form:', formId);

    // Capture values from all renamed inputs before the form is submitted
    gfRepeater_captureRenamedInputValues();

    // Store to hidden field
    if (formId) {
        gfRepeater_storeValuesToHiddenField(formId);
    }

    // Log current state
    console.log('[Repeater2] Values captured before navigation:', gfRepeater_preserved_values);
});

// Hook into GF's confirmation display (for non-AJAX submissions)
jQuery(document).bind('gform_confirmation_loaded', function(event, formId) {
    console.log('[Repeater2] Confirmation loaded for form:', formId);
});
