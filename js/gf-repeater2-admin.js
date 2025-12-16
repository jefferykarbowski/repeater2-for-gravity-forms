var gfRepeater_debug = false;
var gfRepeater_repeater2s = {};
var gfRepeater_page = gf_repeater2_js_admin_strings.page;

function gfRepeater_editforms_getRepeaters() {

	var repeater2Found = 0;
	var repeater2Id = 0;
	var repeater2Children = [];
	var repeater2ChildrenIds = [];
	var repeater2StartId;

	jQuery('.gfield').each(function(){
		if (repeater2Found == 0) {
			if (jQuery(this).has('.gf-repeater2-start').length) {
				repeater2Id += 1;
				repeater2StartId = this.id;
				repeater2Found = 1;
			}
		} else {
			if (jQuery(this).has('.gf-repeater2-start').length) {
				return false;
			}
			if (jQuery(this).has('.gf-repeater2-end').length) {
				gfRepeater_repeater2s[repeater2Id] = {startId:repeater2StartId,childrenIds:repeater2ChildrenIds,children:repeater2Children};
				repeater2Children = [];
				repeater2ChildrenIds = [];
				repeater2Found = 0;
			} else {
				repeater2Children.push(this);
				repeater2ChildrenIds.push(this.id);
			}
		}
	});

	if (repeater2Found !== 0) {
		return false;
	}

	return true;
}

function gfRepeater_getId(id) {
	return id.substr(6);
}

function gfRepeater_getField(id) {
	var idNum = gfRepeater_getId(id);
	return GetFieldById(idNum);
}

function gfRepeater_editforms_getRepeaterId(elementId) {
	jQuery.each(gfRepeater_repeater2s, function(key, value){
		if (jQuery.inArray(elementId, this['childrenIds'])) {
			return key;
		}
	});
	return false;
}

function gfRepeater_editforms_update(leaving) {

	if (!gfRepeater_editforms_getRepeaters()) {
		return;
	}


	if (leaving) {
		UpdateFormObject();
	}


	jQuery.each(gfRepeater_repeater2s, function(key, value){
		var repeater2Id = key;
		var repeater2Field = gfRepeater_getField(this['startId']);
		var repeater2Children = [];
		var requiredChildren = [];


		jQuery.each(this['children'], function(key, value){
			var fieldRequired = gfRepeater_editforms_updateRequired(value, leaving);
			var fieldId = gfRepeater_getId(value.id);
			if (fieldRequired) { requiredChildren.push(fieldId); }
			repeater2Children.push(fieldId);
		});


		repeater2Field['repeater2RequiredChildren'] = requiredChildren;
		repeater2Field['repeater2Children'] = repeater2Children;

	});

}

function gfRepeater_editforms_updateRequired(field, leaving) {
	var fieldId = gfRepeater_getId(field.id);
	var getField = gfRepeater_getField(field.id);
	var gfRequired = getField['isRequired'];
	var repeater2Required = getField['repeater2Field_isRequired'];
	var returnRequired = false;

	if (gfRepeater_debug) {
        console.log(field.id + ' is ' + gfRequired + ' and leaving is ' + leaving);
        console.log(field.id + ' - Before Filter - isRequired:' + getField['isRequired'] + ' - repeater2Field_isRequired:' + getField['repeater2Field_isRequired']);
    }

	if (gfRequired) {
		getField['isRequired'] = true;
		getField['repeater2Field_isRequired'] = true;

        returnRequired = true;

        return returnRequired;
	}
    //

	if (!gfRequired && leaving) {
		getField['repeater2Field_isRequired'] = false;
		if (gfRepeater_debug) { console.log(field.id+' - Used Filter 2'); }
	}

	if (repeater2Required && !leaving) {
		jQuery(field).find('.gfield_required').html('*');
		getField['isRequired'] = true;
		getField['repeater2Field_isRequired'] = false;
		if (gfRepeater_debug) { console.log(field.id+' - Used Filter 3'); }
	}

	if (!repeater2Required && !leaving) {
		jQuery(field).find('.gfield_required').html('');
		getField['isRequired'] = false;
		if (gfRepeater_debug) { console.log(field.id+' - Used Filter 4'); }
	}

	if (gfRepeater_debug) { console.log(field.id+' - After Filter - isRequired:'+getField['isRequired']+' - repeater2Field_isRequired:'+getField['repeater2Field_isRequired']); }

	return returnRequired;
}

function gfRepeater_editforms_updateUpdateButton() {

	// Try multiple selectors for different GF versions
	var updateButton = jQuery('#ajax-save-form-menu-bar, button.update-form, [data-js="ajax-save-form"]');

	if (updateButton.length === 0) {
		return;
	}


	// Try multiple event binding strategies since GF uses AJAX

	// Strategy 1: Use native addEventListener with capture phase
	if (updateButton.get(0)) {
		updateButton.get(0).addEventListener('click', function(e) {
			gfRepeater_editforms_update(true);
		}, true); // true = capture phase
	}

	// Strategy 2: jQuery mousedown (fires before click)
	updateButton.off('mousedown.repeater2').on('mousedown.repeater2', function(e) {
		gfRepeater_editforms_update(true);
	});

	// Strategy 3: Also try regular click with high priority
	updateButton.off('click.repeater2').on('click.repeater2', function(e) {
		gfRepeater_editforms_update(true);
	});
}

jQuery(document).ready(function($) {
	if (gfRepeater_page == 'gf_edit_forms') {
		gfRepeater_editforms_update(false);
		gfRepeater_editforms_updateUpdateButton();
	}
});

// Debug shortcuts
if (gfRepeater_debug) {
	jQuery(window).keydown(function(event){
		// Up Arrow - Prints the contents of gfRepeater_repeater2s into the console.
		if (event.which == 38) { console.log(gfRepeater_repeater2s); }

		// Down Arrow - Loop through all of the fields (even non-repeated fields) and output their settings to the console.
		if (event.which == 40) {
			jQuery('.gfield').each(function(){
				console.log(gfRepeater_getField(this.id));
			});
		}
	});
}