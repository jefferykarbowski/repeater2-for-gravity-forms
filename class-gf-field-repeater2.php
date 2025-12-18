<?php
class GF_Field_Repeater2 extends GF_Field {
    public $type = 'repeater2';

    public static function init_admin() {
        $admin_page = rgget( 'page' );

        if ( $admin_page == 'gf_edit_forms' && ! empty( $_GET['id'] ) ) {
            add_action( 'gform_field_standard_settings' , array( 'GF_Field_Repeater2', 'gform_standard_settings' ), 10, 2 );
            add_action( 'gform_field_appearance_settings' , array( 'GF_Field_Repeater2', 'gform_appearance_settings' ), 10, 2 );
            add_action( 'gform_editor_js_set_default_values', array( 'GF_Field_Repeater2', 'gform_set_defaults' ) );
            add_action( 'gform_editor_js', array( 'GF_Field_Repeater2', 'gform_editor' ) );
            add_filter( 'gform_tooltips', array( 'GF_Field_Repeater2', 'gform_tooltips' ) );
        }

        if ( $admin_page == 'gf_entries' ) {
            add_filter( 'gform_form_post_get_meta', array( 'GF_Field_Repeater2', 'gform_hide_children' ) );
        }
    }

    public static function init_frontend() {
        add_action( 'gform_form_args', array( 'GF_Field_Repeater2', 'gform_disable_ajax' ) );
        add_action( 'gform_enqueue_scripts', array( 'GF_Field_Repeater2', 'gform_enqueue_scripts' ), 10, 2 );
        add_filter( 'gform_pre_render', array( 'GF_Field_Repeater2', 'gform_populate_repeater_from_saved_entry' ), 5 );
        add_filter( 'gform_pre_render', array( 'GF_Field_Repeater2', 'gform_unhide_children_validation' ) );
        add_filter( 'gform_pre_validation', array( 'GF_Field_Repeater2', 'gform_bypass_children_validation' ) );
        add_filter( 'gform_notification', array( 'GF_Field_Repeater2', 'gform_ensure_repeater_data_in_notification' ), 10, 3 );
        add_filter( 'gform_field_value', array( 'GF_Field_Repeater2', 'gform_handle_repeater_file_uploads' ), 10, 3 );
        add_filter( 'gform_field_content', array( 'GF_Field_Repeater2', 'gform_update_repeater_hidden_input' ), 10, 5 );
        add_filter( 'gform_incomplete_submission_pre_save', array( 'GF_Field_Repeater2', 'gform_modify_incomplete_submission_data' ), 10, 3 );
        add_action( 'gform_incomplete_submission_post_save', array( 'GF_Field_Repeater2', 'gform_save_incomplete_submission' ), 10, 4 );
        add_filter( 'gform_incomplete_submission_post_get', array( 'GF_Field_Repeater2', 'gform_restore_incomplete_submission' ), 10, 3 );
        add_filter( 'gform_post_paging', array( 'GF_Field_Repeater2', 'gform_capture_repeater_on_page_change' ), 1, 3 );
        add_filter( 'gform_save_field_value', array( 'GF_Field_Repeater2', 'gform_save_repeater_field_value' ), 10, 5 );
    }

    public static function gform_enqueue_scripts( $form, $is_ajax ) {
        if ( ! empty( $form ) ) {
            if ( GF_Field_Repeater2::get_field_index( $form ) !== false ) {
                wp_enqueue_script( 'gforms_repeater2_postcapture_js', plugins_url( 'js/jquery.postcapture.min.js', __FILE__ ), array( 'jquery' ), '0.0.1' );
                wp_enqueue_script( 'jquery_mask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.js', array( 'jquery' ), '1.14.16' );
                wp_enqueue_script( 'gforms_repeater2_js', plugins_url( 'js/gf-repeater2.js', __FILE__ ), array( 'jquery', 'jquery_mask' ), GF_REPEATER_VERSION );

                wp_enqueue_style( 'gforms_repeater2_css', plugins_url( 'css/gf-repeater2.css', __FILE__ ), array(), GF_REPEATER_VERSION );
            }
        }
    }

    public function get_form_editor_field_title() {
        return 'Repeater';
    }

	public function get_form_editor_field_settings() {
		return array(
			'admin_label_setting',
			'css_class_setting',
			'description_setting',
			'error_message_setting',
			'label_setting',
			'prepopulate_field_setting',
			'conditional_logic_field_setting',
		);
	}

	public static function gform_set_defaults() {
		echo "
			case \"repeater2\" :
				field.label = \"Repeater\";
			break;
		";
	}

	public static function gform_standard_settings($position, $form_id) {
		if ($position == 1600) {
			echo "<li class=\"repeater2_settings field_setting\">
					<label for=\"field_repeater2_start\">Start ";

			gform_tooltip('form_field_repeater2_start');

			echo "	</label>
					<input type=\"number\" id=\"field_repeater2_start\" min=\"1\" value=\"1\" onchange=\"SetFieldProperty('start', this.value);\">
				</li>";

			echo "<li class=\"repeater2_settings field_setting\">
					<label for=\"field_repeater2_min\">Min ";

			gform_tooltip('form_field_repeater2_min');

			echo "	</label>
					<input type=\"number\" id=\"field_repeater2_min\" min=\"1\" value=\"1\" onchange=\"SetFieldProperty('min', this.value);\">
				</li>";

			echo "<li class=\"repeater2_settings field_setting\">
					<label for=\"field_repeater2_max\">Max ";

			gform_tooltip('form_field_repeater2_max');

			echo "	</label>
					<input type=\"number\" id=\"field_repeater2_max\" min=\"1\" onchange=\"SetFieldProperty('max', this.value);\">
				</li>";
		}
	}

	public static function gform_appearance_settings($position, $form_id) {
		if ($position == 400) {
			echo "<li class=\"repeater2_settings field_setting\">
					<input type=\"checkbox\" id=\"field_repeater2_hideLabel\" onchange=\"SetFieldProperty('hideLabel', this.checked);\"> 
					<label for=\"field_repeater2_hideLabel\" class=\"inline\">Hide Label & Description ";

			gform_tooltip('form_field_repeater2_hideLabel');

			echo "	</label>
				</li>";
		}
	}

	public static function gform_editor() {
		echo "<script type=\"text/javascript\">
				fieldSettings['repeater2'] += ', .repeater2_settings';
				jQuery(document).bind('gform_load_field_settings', function(event, field, form){
					jQuery('#field_repeater2_start').val(field['start']);
					jQuery('#field_repeater2_min').val(field['min']);
					jQuery('#field_repeater2_max').val(field['max']);
					jQuery('#field_repeater2_hideLabel').prop('checked', field['hideLabel']);
				});
			</script>";
	}

	public static function gform_tooltips($tooltips) {
		$tooltips['form_field_repeater2_start'] = "The number of times the repeater2 will be repeated when the form is rendered. Leaving this field blank or setting it to a number higher than the maximum number is the same as setting it to 1.";
		$tooltips['form_field_repeater2_min'] = "The minimum number of times the repeater2 is allowed to be repeated. Leaving this field blank or setting it to a number higher than the maximum field is the same as setting it to 1.";
		$tooltips['form_field_repeater2_max'] = "The maximum number of times the repeater2 is allowed to be repeated. Leaving this field blank or setting it to a number lower than the minimum field is the same as setting it to unlimited.";
		$tooltips['form_field_repeater2_hideLabel'] = "If this is checked, the repeater2 label and description will not be shown to users on the form.";
		return $tooltips;
	}

	function validate($value, $form) {
		$repeater2_required = $this->repeater2RequiredChildren;

		if (!empty($repeater2_required)) {
			$dataArray = json_decode($value, true);

			foreach ($form['fields'] as $key=>$value) {
				$fieldKeys[$value['id']] = $key;

				if (is_array($value['inputs'])) {
					foreach ($value['inputs'] as $inputKey=>$inputValue) {
						$inputKeys[$value['id']][$inputValue['id']] = $inputKey;
					}
				}
			}

			if ($dataArray['repeatCount'] < $this->min) {
				$this->failed_validation  = true;
				$this->validation_message = "A minimum number of ".$this->min." is required.";
				return;
			}

			if ($this->max && $dataArray['repeatCount'] > $this->max) {
				$this->failed_validation  = true;
				$this->validation_message = "A maximum number of ".$this->max." is allowed.";
				return;
			}

			for ($i = 1; $i < $dataArray['repeatCount'] + 1; $i++) {
				foreach ($dataArray['children'] as $field_id=>$field) {
					$inputNames = $field['inputs'];
					$repeatSkips = rgars($field, 'conditionalLogic/skip');


					if (!is_array($inputNames)) { continue; }

					if (is_array($repeatSkips)) {
						if (in_array($i, $repeatSkips) || in_array('all', $repeatSkips)) { continue; }
					}

					foreach ($inputNames as $inputName) {
						if (is_array($inputName)) { $inputName = reset($inputName); }

						if (substr($inputName, -2) == '[]') {
							$getInputName = substr($inputName, 0, strlen($inputName) - 2).'-'.$dataArray['repeater2Id'].'-'.$i;
						} else {
							$getInputName = $inputName.'-'.$dataArray['repeater2Id'].'-'.$i;
						}

						$getInputName = str_replace('.', '_', strval($getInputName));
						$getInputData = rgpost($getInputName);
						$getInputIdNum = preg_split("/(_|-)/", $getInputName);

						if (in_array($getInputIdNum[1], $repeater2_required)) {
							$fieldKey = $fieldKeys[$getInputIdNum[1]];
							$fieldType = $form['fields'][$fieldKey]['type'];
							$failedValidation = false;

							switch($fieldType) {
								case 'name':
									$requiredIDs = array(3, 6);
									if (in_array($getInputIdNum[2], $requiredIDs) && empty($getInputData)) { $failedValidation = true; }
									break;
								case 'address':
									$skipIDs = array(2);
									if (!in_array($getInputIdNum[2], $skipIDs) && empty($getInputData)) { $failedValidation = true; }
									break;
								default:
									if (empty($getInputData)) { $failedValidation = true; }
							}

							if ($failedValidation) {
								$this->failed_validation  = true;
								if ($this->errorMessage) { $this->validation_message = $this->errorMessage; } else { $this->validation_message = "A required field was left blank."; }
								return;
							}
						}
					}
				}
			}
		}
	}

	public function get_field_content( $value, $force_frontend_label, $form ) {
		if ( is_admin() ) {
			$admin_buttons = $this->get_admin_buttons();
			$field_content = "{$admin_buttons}
				<div class=\"gf-pagebreak-first gf-pagebreak-container gf-repeater2 gf-repeater2-start\">
					<div class=\"gf-pagebreak-text-before\">Begin Repeater</div>
					<div class=\"gf-pagebreak-text-main\"><span>REPEATER</span></div>
					<div class=\"gf-pagebreak-text-after\">Top of Repeater</div>
				</div>";
		} else {
			$field_label		= $this->get_field_label($force_frontend_label, $value);
			$description		= $this->get_description($this->description, 'gsection_description gf_repeater2_description');
			$hide_label			= $this->hideLabel;
			$validation_message = ( $this->failed_validation && ! empty( $this->validation_message ) ) ? sprintf( "<div class='gfield_description validation_message'>%s</div>", $this->validation_message ) : '';
			if (!empty($field_label)) { $field_label = "<h2 class='gf_repeater2_title'>{$field_label}</h2>"; } else { $field_label = ''; }
			if ($hide_label) { $field_label = ''; $description = ''; }
			$field_content = "<div class=\"ginput_container ginput_container_repeater2\">{$field_label}{FIELD}</div>{$description}{$validation_message}";
		}
		return $field_content;
	}

	public function get_field_input($form, $value = '', $entry = null) {
		if (is_admin()) {
			return '';
		} else {

			$form_id			= $form['id'];
			$is_entry_detail	= $this->is_entry_detail();
			$is_form_editor		= $this->is_form_editor();
			$id					= (int) $this->id;
			$field_id			= $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
			$tabindex  			= $this->get_tabindex();
			$repeater2_parem		= $this->inputName;
			$repeater2_start		= $this->start;
			$repeater2_min		= $this->min;
			$repeater2_max		= $this->max;
			$repeater2_required	= $this->repeater2RequiredChildren;
			$repeater2_children	= $this->repeater2Children;

			if (!empty($repeater2_parem)) {
				$repeater2_parem_value = GFFormsModel::get_parameter_value($repeater2_parem, $value, $this);
				if (!empty($repeater2_parem_value)) { $repeater2_start = $repeater2_parem_value; }
			}

			if (!empty($repeater2_children)) {
				$repeater2_children_info = array();
				$repeater2_parems = GF_Field_Repeater2::get_children_parem_values($form, $repeater2_children);

				foreach($repeater2_children as $repeater2_child) {
					$repeater2_children_info[$repeater2_child] = array();
					$repeater2_child_field_index = GF_Field_Repeater2::get_field_index($form, 'id', $repeater2_child);

					if (!empty($repeater2_required)) {
						if (in_array($repeater2_child, $repeater2_required)) {
							$repeater2_children_info[$repeater2_child]['required'] = true;
						}
					}

					if (!empty($repeater2_parems)) {
						if (array_key_exists($repeater2_child, $repeater2_parems)) {
							$repeater2_children_info[$repeater2_child]['prePopulate'] = $repeater2_parems[$repeater2_child];
						}
					}

					if ($repeater2_child_field_index !== false) {
						if ($form['fields'][$repeater2_child_field_index]['inputMask']) {
							$repeater2_children_info[$repeater2_child]['inputMask'] = $form['fields'][$repeater2_child_field_index]['inputMaskValue'];
						} elseif ($form['fields'][$repeater2_child_field_index]['type'] == 'phone' && $form['fields'][$repeater2_child_field_index]['phoneFormat'] = 'standard') {
							$repeater2_children_info[$repeater2_child]['inputMask'] = "(999) 999-9999";
						}

						if ($form['fields'][$repeater2_child_field_index]['conditionalLogic']) {
							$repeater2_children_info[$repeater2_child]['conditionalLogic'] = $form['fields'][$repeater2_child_field_index]['conditionalLogic'];
						}
					}
				}

				$repeater2_children = $repeater2_children_info;
			}

			// Check if $value contains saved iteration data (Save and Continue restoration)
			// The saved data has numeric keys like "1", "2" for iterations
			// We need to transform this into metadata format with prePopulate
			// If $value is empty, check defaultValue (set by gform_populate_repeater_from_saved_entry)
			$data_to_transform = !empty($value) ? $value : (isset($this->defaultValue) ? $this->defaultValue : '');

			if (!empty($data_to_transform)) {
				$decoded = json_decode($data_to_transform, true);
				// Check if this is saved iteration data (has numeric keys and nested field data)
				if (is_array($decoded) && !isset($decoded['formId']) && !isset($decoded['children'])) {
					// This is saved iteration data, transform it to metadata format
					$dataArray = $decoded;
					$children_meta = array();

					// Build the children metadata with prePopulate data
					foreach ($dataArray as $iteration => $childValues) {
						if (!is_numeric($iteration)) continue; // Skip non-iteration keys

						foreach ($childValues as $child_field_id => $inputData) {
							if (!isset($children_meta[$child_field_id])) {
								$children_meta[$child_field_id] = array('prePopulate' => array());

								// Copy child settings from repeater2_children if available
								if (isset($repeater2_children[$child_field_id])) {
									if (isset($repeater2_children[$child_field_id]['required'])) {
										$children_meta[$child_field_id]['required'] = $repeater2_children[$child_field_id]['required'];
									}
									if (isset($repeater2_children[$child_field_id]['inputMask'])) {
										$children_meta[$child_field_id]['inputMask'] = $repeater2_children[$child_field_id]['inputMask'];
									}
									if (isset($repeater2_children[$child_field_id]['conditionalLogic'])) {
										$children_meta[$child_field_id]['conditionalLogic'] = $repeater2_children[$child_field_id]['conditionalLogic'];
									}
								}
							}

							// Store the data for this iteration in prePopulate
							if (is_array($inputData) && count($inputData) === 1) {
								// Single-value field
								if ($inputData[0] !== '') {
									$children_meta[$child_field_id]['prePopulate'][$iteration] = $inputData[0];
								}
							} elseif (is_array($inputData) && count($inputData) > 1) {
								// Multi-input field (name, address, etc.)
								$child_field_index = GF_Field_Repeater2::get_field_index($form, 'id', $child_field_id);
								if ($child_field_index !== false) {
									$child_field = $form['fields'][$child_field_index];
									if (isset($child_field->inputs) && is_array($child_field->inputs)) {
										foreach ($child_field->inputs as $input_index => $input) {
											$sub_input_id = str_replace($child_field_id . '.', '', $input['id']);
											if (!isset($children_meta[$child_field_id]['prePopulate'][$sub_input_id])) {
												$children_meta[$child_field_id]['prePopulate'][$sub_input_id] = array();
											}
											if (isset($inputData[$input_index]) && $inputData[$input_index] !== '') {
												$children_meta[$child_field_id]['prePopulate'][$sub_input_id][$iteration] = $inputData[$input_index];
											}
										}
									}
								}
							} elseif (!is_array($inputData) && $inputData !== '[gfRepeater-section]' && $inputData !== '') {
								// Non-array value
								$children_meta[$child_field_id]['prePopulate'][$iteration] = $inputData;
							}
						}
					}

					// Build the metadata structure
					$value = array();
					$value['formId'] = $form_id;
					$value['start'] = count($dataArray);
					if (!empty($repeater2_min)) { $value['min'] = $repeater2_min; }
					if (!empty($repeater2_max)) { $value['max'] = $repeater2_max; }
					$value['children'] = $children_meta;

					$value = json_encode($value);
				}
			}

			if (empty($value)) {
                $value = array();
				$value['formId'] = $form_id;
				if (!empty($repeater2_start)) { $value['start'] = $repeater2_start; }
				if (!empty($repeater2_min)) { $value['min'] = $repeater2_min; }
				if (!empty($repeater2_max)) { $value['max'] = $repeater2_max; }
				if (!empty($repeater2_children)) { $value['children'] = $repeater2_children; }

				$value = json_encode($value);
			}

			return sprintf("<input name='input_%d' id='%s' type='hidden' class='gform_repeater2' value='%s' %s />", $id, $field_id, $value, $tabindex);
		}
	}

	public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead) {
		// Debug logging
		$debug_log = WP_CONTENT_DIR . '/repeater-debug.log';
		$debug_enabled = true;

		if ( $debug_enabled ) {
			file_put_contents( $debug_log, "\n\n=== " . date('Y-m-d H:i:s') . " get_value_save_entry ===\n", FILE_APPEND );
			file_put_contents( $debug_log, "Input name: " . $input_name . "\n", FILE_APPEND );
			file_put_contents( $debug_log, "Raw value: " . substr( $value, 0, 300 ) . "...\n", FILE_APPEND );
		}

		$dataArray = json_decode($value, true);

		if ( $debug_enabled ) {
			file_put_contents( $debug_log, "Decoded dataArray: " . print_r( $dataArray, true ) . "\n", FILE_APPEND );
		}

		if ( ! $dataArray || ! isset( $dataArray['repeatCount'] ) || ! isset( $dataArray['children'] ) ) {
			if ( $debug_enabled ) {
				file_put_contents( $debug_log, "ERROR: Invalid dataArray structure - missing repeatCount or children\n", FILE_APPEND );
			}
			return $value;
		}

		$value = Array();

		for ($i = 1; $i < $dataArray['repeatCount'] + 1; $i++) {
			foreach ($dataArray['children'] as $field_id=>$field) {
				$inputData = Array();

				if (array_key_exists('inputs', $field)) {
					$inputNames = $field['inputs'];
					$repeatSkips = rgars($field, 'conditionalLogic/skip');


					if (is_array($repeatSkips)) {
						if (in_array($i, $repeatSkips) || in_array('all', $repeatSkips)) { continue; }
					}
					
					if (is_array($inputNames)) {
						foreach ($inputNames as $inputName) {
							if (substr($inputName, -2) == '[]') {
								$getInputName = substr($inputName, 0, strlen($inputName) - 2).'-'.$dataArray['repeater2Id'].'-'.$i;
							} else {
								$getInputName = $inputName.'-'.$dataArray['repeater2Id'].'-'.$i;
							}

							$getInputName_clean = str_replace('.', '_', strval($getInputName));
							$getInputData = rgpost($getInputName_clean);

							// If not in POST, try to get from prePopulate (for fields on previous pages)
							if (empty($getInputData) && isset($field['prePopulate'])) {
								// Check if this is a sub-input field (e.g., input_172.3)
								$inputNameParts = explode('.', $inputName);
								if (count($inputNameParts) > 1) {
									// Multi-input field - prePopulate structure is [sub_input_id][iteration]
									$sub_input_id = $inputNameParts[1];
									if (isset($field['prePopulate'][$sub_input_id][$i])) {
										$getInputData = $field['prePopulate'][$sub_input_id][$i];
									}
								} else {
									// Simple field - prePopulate structure is [iteration]
									if (isset($field['prePopulate'][$i])) {
										$getInputData = $field['prePopulate'][$i];
									}
								}
							}

							if ( $debug_enabled && $i == 1 ) {
								file_put_contents( $debug_log, "Looking for: " . $getInputName_clean . " = " . ( $getInputData ? print_r($getInputData, true) : 'EMPTY' ) . "\n", FILE_APPEND );
							}

							// Handle file uploads specially - check for file upload field type
							$fieldType = GF_Field_Repeater2::get_field_type($form, $field_id);
							if ($fieldType == 'fileupload') {
								// For file uploads, we need to process the $_FILES array
								$file_input_name = $getInputName_clean;
								if (isset($_FILES[$file_input_name]) && !empty($_FILES[$file_input_name]['name'])) {
									$file_data = $_FILES[$file_input_name];
									if ($file_data['error'] == 0) {
										// Store the file information with proper URL
										$upload_dir = wp_upload_dir();
										$file_url = $upload_dir['baseurl'] . '/gravity_forms/' . $form['id'] . '/' . $file_data['name'];
										$inputData[] = '<a href="' . esc_url($file_url) . '" target="_blank">' . esc_html($file_data['name']) . '</a>';
									}
								}
							} else {
								// Handle regular field data
								if (!empty($getInputData)) {
									if (is_array($getInputData)) {
										// Special handling for time fields
										if ($fieldType == 'time' && count($getInputData) == 2) {
											// Format time as HH:MM
											$hours = str_pad($getInputData[0], 2, '0', STR_PAD_LEFT);
											$minutes = str_pad($getInputData[1], 2, '0', STR_PAD_LEFT);
											$inputData[] = $hours . ':' . $minutes;
										} else {
											foreach ($getInputData as $theInputData) {
												$inputData[] = $theInputData;
											}
										}
									} else {
										$inputData[] = $getInputData;
									}
								}
							}
						}
					}
				} else {
					if (GF_Field_Repeater2::get_field_type($form, $field_id) == 'section') { $inputData = '[gfRepeater-section]'; }
				}

				$childValue[$field_id] = $inputData;
			}
			$value[$i] = $childValue;
		}

		if ( $debug_enabled ) {
			file_put_contents( $debug_log, "Final value to save: " . print_r( $value, true ) . "\n", FILE_APPEND );
		}

		// Ensure proper serialization for WordPress 6.8 compatibility
		return wp_json_encode($value);
	}

	public function get_value_entry_list($value, $entry, $field_id, $columns, $form) {
		if (empty($value)) {
			return '';
		} else {
			// Handle both old serialized format and new JSON format
			if (is_serialized($value)) {
				$dataArray = GFFormsModel::unserialize($value);
			} else {
				$dataArray = json_decode($value, true);
			}
			$arrayCount = count($dataArray);
			if ($arrayCount > 1) { $returnText = $arrayCount.' entries'; } else { $returnText = $arrayCount.' entry'; }
			return $returnText;
		}
	}

	public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen') {
		if (empty($value)) {
			return '';
		} else {
			// Handle both old serialized format and new JSON format
			if (is_serialized($value)) {
				$dataArray = GFFormsModel::unserialize($value);
			} else {
				$dataArray = json_decode($value, true);
			}
			$arrayCount = count($dataArray);
			$output = "\n";
			$count = 0;
			$repeatCount = 0;
			$display_empty_fields = rgget('gf_display_empty_fields', $_COOKIE);
			$form_id = $this->formId;
			$get_form = GFFormsModel::get_form_meta_by_id($form_id);
			$form = $get_form[0];

			foreach ($dataArray as $key=>$value) {
				$repeatCount++;
				$tableContents = '';

				if (!empty($value) && !is_array($value)) {
					$save_value = $value;
					unset($value);
					$value[0] = $save_value;
				}

				foreach ($value as $childKey => $childValue) {
					$count++;
					$childValueOutput = '';
					
					if (empty($display_empty_fields) && count((array) $childValue) == 0) {
                        continue;
                    }

					if (is_numeric($childKey)) {
						$field_index = GF_Field_Repeater2::get_field_index($form, 'id', $childKey);
						if ($field_index === false) { continue; }
						$entry_title = $form['fields'][$field_index]['label'];
					} else {
						$entry_title = $childKey;
					}

					$entry_title = str_replace('[gfRepeater-count]', $repeatCount, $entry_title);

					if ($format == 'html') {
						if ($childValue == '[gfRepeater-section]') {
							if ($media == 'email') {
								$tableStyling = ' style="font-size:14px;font-weight:bold;background-color:#eee;border-bottom:1px solid #dfdfdf;padding:7px 7px"';
							} else {
								$tableStyling = ' class="entry-view-section-break"';
							}
						} else {
							if ($media == 'email') {
								$tableStyling = ' style="background-color:#EAF2FA;font-family:sans-serif;font-size:12px;font-weight:bold"';
							} else {
								$tableStyling = ' class="entry-view-field-name"';
							}
						}

						$tableContents .= "<tr>\n<td colspan=\"2\"".$tableStyling.">".$entry_title."</td>\n</tr>\n";
					} else {
						$tableContents .= $entry_title.": ";
					}

					if (is_array($childValue)) {
						if (count($childValue) == 1) {
							$childValueOutput = $childValue[0];
						} elseif (count($childValue) > 1) {
							// Check if this is a time field (2 values: hours and minutes)
							$field_index = GF_Field_Repeater2::get_field_index($form, 'id', $childKey);
							$is_time_field = false;
							if ($field_index !== false && $form['fields'][$field_index]['type'] == 'time') {
								$is_time_field = true;
							}
							
							if ($is_time_field && count($childValue) == 2) {
								// Format time as HH:MM
								$hours = str_pad($childValue[0], 2, '0', STR_PAD_LEFT);
								$minutes = str_pad($childValue[1], 2, '0', STR_PAD_LEFT);
								$childValueOutput = $hours . ':' . $minutes;
							} else {
								if ($format == 'html') {
									if ($media == 'email') {
										$childValueOutput = "<ul style=\"list-style:none;margin:0;padding:0;\">\n";
									} else {
										$childValueOutput = "<ul>\n";
									}
								}

								foreach ($childValue as $childValueData) {
									if ($format == 'html') {
										$childValueOutput .= "<li>".$childValueData."</li>";
									} else {
										$childValueOutput .= $childValueData."\n";
									}
								}
								
								if ($format == 'html') { $childValueOutput .= "</ul>\n"; }
							}
						}

						if ($media == 'email') { $tableStyling = ''; } else { $tableStyling = ' class=\"entry-view-field-value\"'; }

						if ($format == 'html') {
							$tableContents .= "<tr>\n<td colspan=\"2\"".$tableStyling.">".$childValueOutput."</td>\n</tr>\n";
						} else {
							$tableContents .= $childValueOutput."\n";
						}
					}
				}

				if (!empty($tableContents)) {
					if ($format == 'html') {
						if ($media == 'email') { $tableStyling = ' width="100%" border="0" cellpadding="5" bgcolor="#FFFFFF"'; } else { $tableStyling = ' class="widefat fixed entry-detail-view"'; }
						$output .= "<table cellspacing=\"0\"".$tableStyling.">\n";
						$output .= $tableContents;
						$output .= "</table>\n";
					} else {
						$output .= $tableContents."\n";
					}
				}
			}
		}

		if ($count !== 0) {
			if ($format == 'text') { $output = rtrim($output); }
			return $output;
		} else { return ''; }
	}

	public function get_value_merge_tag($value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br) {
		$output = GF_Field_Repeater2::get_value_entry_detail($raw_value, '', false, $format, 'email');
		$output = preg_replace("/[\r\n]+/", "\n", $output);
		
		// Ensure all repeater data is included in emails
		if (empty($output) && !empty($raw_value)) {
			$output = $this->get_value_entry_detail($raw_value, '', false, $format, 'email');
		}
		
		return trim($output);
	}

	public function get_value_export($entry, $input_id = '', $use_text = false, $is_csv = false) {
		if (empty($input_id)) { $input_id = $this->id; }
		$output = rgar($entry, $input_id);
		$output = GF_Field_Repeater2::get_value_entry_detail($output, '', false, 'text', 'email');
		$output = preg_replace("/[\r\n]+/", ", ", trim($output));
		return $output;
	}

	public static function gform_hide_children($form) {
		$form_id = $form['id'];
		$repeater2Children = Array();
		$grid_modified = false;
		$grid_meta = GFFormsModel::get_grid_column_meta($form_id);

		foreach($form['fields'] as $key=>$field) {
			if ($field->type == 'repeater2') {
				if (is_array($field->repeater2Children)) { $repeater2Children = array_merge($repeater2Children, $field->repeater2Children); }
			} elseif ($field->type == 'repeater2-end') { array_push($repeater2Children, $field->id); }

			if (!empty($repeater2Children)) {
				if (in_array($field->id, $repeater2Children)) {
					unset($form['fields'][$key]);

					if (is_array($grid_meta)) {
						$grid_pos = array_search($field->id, $grid_meta);
						if ($grid_pos) {
							$grid_modified = true;
							unset($grid_meta[$grid_pos]);
						}
					}
				}
			}
		}

		if ($grid_modified) { GFFormsModel::update_grid_column_meta($form_id, $grid_meta); }

		$form['fields'] = array_values($form['fields']);

		return $form;
	}

	public static function gform_disable_ajax($args) {
		$get_form = GFFormsModel::get_form_meta_by_id($args['form_id']);
		$form = reset($get_form);

		if (GF_Field_Repeater2::get_field_index($form) !== false) {
			$args['ajax'] = false;
		}

		return $args;
	}

	public static function gform_bypass_children_validation($form) {
		if (GF_Field_Repeater2::get_field_index($form) === false) { return $form; }

		$repeater2Children = Array();

		foreach($form['fields'] as $key=>$field) {
			if ($field->type == 'repeater2') {
				if (is_array($field->repeater2Children)) { $repeater2Children = array_merge($repeater2Children, $field->repeater2Children); }
			}

			if (!empty($repeater2Children)) {
				if (in_array($field->id, $repeater2Children) && !$field->adminOnly) {
					$form['fields'][$key]['adminOnly'] = true;
					$form['fields'][$key]['repeater2ChildValidationHidden'] = true;
				}
			}
		}

		return $form;
	}

	public static function gform_unhide_children_validation($form) {
		if (GF_Field_Repeater2::get_field_index($form) === false) { return $form; }
		
		foreach($form['fields'] as $key=>$field) {
			if ($field->repeater2ChildValidationHidden) {
				$form['fields'][$key]['adminOnly'] = false;
				$form['fields'][$key]['repeater2ChildValidationHidden'] = false;
			}
		}

		return $form;
	}

	public static function gform_ensure_repeater_data_in_notification( $notification, $form, $entry ) {
		// Ensure repeater field data is properly included in email notifications
		foreach ( $form['fields'] as $field ) {
			if ( $field->type == 'repeater2' ) {
				$field_value = rgar( $entry, $field->id );
				if ( ! empty( $field_value ) ) {
					// Force the field to process its data for email display
					$field->get_value_entry_detail( $field_value, '', false, 'html', 'email' );
				}
			}
		}
		return $notification;
	}

	public static function gform_handle_repeater_file_uploads( $value, $field, $form ) {
		// Handle file uploads in repeater context
		if ( $field->type == 'fileupload' && ! empty( $_FILES ) ) {
			$field_id = $field->id;
			$input_name = 'input_' . $field_id;
			
			// Check if this is a repeater field by looking for the naming pattern
			foreach ( $_FILES as $file_key => $file_data ) {
				if ( strpos( $file_key, $input_name ) === 0 && ! empty( $file_data['name'] ) ) {
					// This is a file upload from a repeater field
					if ( $file_data['error'] == 0 ) {
						// Process the file upload using Gravity Forms methods
						$uploaded_file = GFFormsModel::get_temp_filename( $form['id'], $file_data['name'] );
						if ( $uploaded_file ) {
							// Return the file with proper URL for display
							$upload_dir = wp_upload_dir();
							$file_url = $upload_dir['baseurl'] . '/gravity_forms/' . $form['id'] . '/' . $file_data['name'];
							return '<a href="' . esc_url($file_url) . '" target="_blank">' . esc_html($file_data['name']) . '</a>';
						}
					}
				}
			}
		}
		
		return $value;
	}

	/**
	 * Update repeater hidden input value in rendered HTML for Save and Continue
	 * This ensures repeater fields are populated on all pages, including previous pages
	 *
	 * @param string $field_content The field content to be filtered
	 * @param GF_Field $field The field object
	 * @param string $value The field value
	 * @param int $lead_id The entry ID
	 * @param int $form_id The form ID
	 * @return string The modified field content
	 */
	public static function gform_update_repeater_hidden_input( $field_content, $field, $value, $lead_id, $form_id ) {

		// Only process repeater2 fields
		if ( $field->type !== 'repeater2' ) {
			return $field_content;
		}


		// Get resume token from URL or cookie
		$resume_token = null;

		if ( isset( $_GET['gf_token'] ) ) {
			$resume_token = sanitize_text_field( $_GET['gf_token'] );
		} elseif ( ! empty( $_COOKIE['gf_resume_token_' . $form_id] ) ) {
			$resume_token = sanitize_text_field( $_COOKIE['gf_resume_token_' . $form_id] );
		}

		if ( ! $resume_token ) {
			return $field_content;
		}

		// Get the saved submission data
		$submission_data = GFFormsModel::get_draft_submission_values( $resume_token );

		if ( empty( $submission_data ) || empty( $submission_data['submission'] ) ) {
			return $field_content;
		}

		$submission = json_decode( $submission_data['submission'], true );
		if ( empty( $submission['partial_entry'][ $field->id ] ) ) {
			return $field_content;
		}

		$saved_data = $submission['partial_entry'][ $field->id ];

		// Parse the saved data to build metadata with prePopulate
		$dataArray = json_decode( $saved_data, true );
		if ( ! $dataArray || ! is_array( $dataArray ) ) {
			return $field_content;
		}

		// Build the metadata structure
		$children_meta = array();
		foreach ( $dataArray as $iteration => $childValues ) {
			if ( ! is_numeric( $iteration ) ) continue;

			foreach ( $childValues as $child_field_id => $inputData ) {
				if ( ! isset( $children_meta[ $child_field_id ] ) ) {
					$children_meta[ $child_field_id ] = array( 'prePopulate' => array() );
				}

				// Store the data for this iteration
				if ( is_array( $inputData ) && count( $inputData ) === 1 ) {
					$children_meta[ $child_field_id ]['prePopulate'][ $iteration ] = $inputData[0];
				} elseif ( ! is_array( $inputData ) && $inputData !== '[gfRepeater-section]' && $inputData !== '' ) {
					$children_meta[ $child_field_id ]['prePopulate'][ $iteration ] = $inputData;
				}
			}
		}

		// Create the metadata JSON
		$metadata = array(
			'formId' => $form_id,
			'start' => count( $dataArray ),
			'children' => $children_meta
		);
		$metadata_json = json_encode( $metadata );
		$metadata_json_escaped = esc_attr( $metadata_json );


		// Replace the hidden input value in the field content
		// The hidden input looks like: <input name='input_5' id='input_30_5' type='hidden' class='gform_repeater2' value='...' />
		$pattern = '/<input\s+([^>]*?)class=[\'"]gform_repeater2[\'"]([^>]*?)value=[\'"][^\'\"]*[\'"]([^>]*?)\/>/i';
		$replacement = '<input $1class="gform_repeater2"$2value="' . $metadata_json_escaped . '"$3/>';

		$updated_content = preg_replace( $pattern, $replacement, $field_content );

		if ( $updated_content !== $field_content ) {
			return $updated_content;
		} else {
			return $field_content;
		}
	}

	public static function get_field_index($form, $key = 'type', $value = 'repeater2') {
		if (is_array($form)) {
			if (!array_key_exists('fields', $form)) { return false; }
		} else { return false; }

		foreach ($form['fields'] as $field_key=>$field_value) {
			if (is_object($field_value)) {
				if (property_exists($field_value, $key)) {
					if ($field_value[$key] == $value) { return $field_key; }
				}
			}
		}

		return false;
	}

	public static function get_field_type($form, $id) {
		$field_index = GF_Field_Repeater2::get_field_index($form, 'id', $id);
		if ($field_index !== false) { return $form['fields'][$field_index]['type']; }
		return false;
	}

	public static function get_children_parems($form, $children_ids) {
		foreach($form['fields'] as $key=>$value) {
			if (in_array($value['id'], $children_ids)) {
				if ($value['inputName']) {
					$parems[$value['id']] = $value['inputName'];
				} elseif ($value['inputs']) {
					foreach($value['inputs'] as $key=>$value) {
						if ($value['name']) { $parems[$value['id']] = $value['name']; }
					}
				}
			}
		}
		if (!empty($parems)) { return $parems; } else { return false; }
	}

	public static function get_children_parem_values($form, $children_ids) {
		global $wp_filter;
		$children_parems = GF_Field_Repeater2::get_children_parems($form, $children_ids);

		if (empty($children_parems)) { return false; }

		// Check the URL first
		foreach($_GET as $url_key=>$url_value) {
			$key = array_search($url_key, $children_parems);
			if ($key !== false) {
				$parems[$key][0] = $url_value;
			} else {
				// Match parameter name and iteration number (e.g., "relationship1" or "relationship_1")
				if (preg_match('/^(.+?)_?(\d+)$/', $url_key, $matches)) {
					$param_name = $matches[1];
					$iteration = $matches[2];
					$key = array_search($param_name, $children_parems);
					if ($key !== false) { $parems[$key][$iteration] = $url_value; }
				}
			}
		}

		// Then check the filters
		foreach($wp_filter as $key=>$value) {
			$split_key = preg_split('/^gform_field_value_+\K/', $key);
			if (!empty($split_key[1])) {
				$key1 = array_search($split_key[1], $children_parems);
				if ($key1 !== false) {
					$parems[$key1][0] = apply_filters($key, '');
				} else {
					// Match parameter name and iteration number (e.g., "relationship1" or "relationship_1")
					if (preg_match('/^(.+?)_?(\d+)$/', $split_key[1], $matches)) {
						$param_name = $matches[1];
						$iteration = $matches[2];
						$key2 = array_search($param_name, $children_parems);
						if ($key2 !== false) { $parems[$key2][$iteration] = apply_filters($key, ''); }
					}
				}
			}
		}
		if (!empty($parems)) { return $parems; } else { return false; }
	}

	/**
	 * Save repeater field data for incomplete submissions (Save and Continue)
	 * This ensures that dynamically renamed repeater child fields are properly saved
	 *
	 * @param array $submission The incomplete submission data
	 * @param string $resume_token The unique token for resuming
	 * @param array $form The form object
	 * @param array $entry The partial entry data
	 */
	public static function gform_populate_repeater_from_saved_entry( $form ) {
		// Get resume token from URL or cookie
		$resume_token = null;

		if ( isset( $_GET['gf_token'] ) ) {
			$resume_token = sanitize_text_field( $_GET['gf_token'] );
		} elseif ( ! empty( $_COOKIE['gf_resume_token_' . $form['id']] ) ) {
			$resume_token = sanitize_text_field( $_COOKIE['gf_resume_token_' . $form['id']] );
		}

		// Only run if we have a resume token
		if ( ! $resume_token ) {
			return $form;
		}
		$submission_data = GFFormsModel::get_draft_submission_values( $resume_token );

		if ( empty( $submission_data ) || empty( $submission_data['submission'] ) ) {
			return $form;
		}

		$submission = json_decode( $submission_data['submission'], true );
		if ( empty( $submission['partial_entry'] ) ) {
			return $form;
		}

		$partial_entry = $submission['partial_entry'];

		//Loop through form fields to find and populate repeater fields
		foreach ( $form['fields'] as &$field ) {
			if ( $field->type !== 'repeater2' ) {
				continue;
			}

			$field_id = $field->id;

			// Check if we have saved data for this repeater field
			if ( ! isset( $partial_entry[ $field_id ] ) || empty( $partial_entry[ $field_id ] ) ) {
				continue;
			}

			$saved_data = $partial_entry[ $field_id ];

			// Set the field's default value to the saved data
			// This will be passed to get_field_input() as the $value parameter
			$field->defaultValue = $saved_data;
		}

		return $form;
	}

	public static function gform_capture_repeater_on_page_change( $form, $source_page, $current_page ) {
		// This hook fires when navigating between pages (Next/Previous)
		// We capture renamed repeater inputs and store them in transients for later retrieval during save
		// IMPORTANT: Only store data for fields on the SOURCE page (the page we're leaving)

		// Check for resume token in POST
		$resume_token = isset( $_POST['gform_resume_token'] ) ? $_POST['gform_resume_token'] : null;

		// Get fields on the source page
		$source_page_fields = array();
		foreach ( $form['fields'] as $field ) {
			if ( isset( $field->pageNumber ) && $field->pageNumber == $source_page ) {
				$source_page_fields[] = $field->id;
			}
		}

		foreach ( $form['fields'] as $field ) {
			if ( $field->type !== 'repeater2' ) {
				continue;
			}

			$field_id = $field->id;

			// ONLY process repeater fields on the source page (the page we're leaving)
			if ( ! in_array( $field_id, $source_page_fields ) ) {
				continue;
			}

			$iteration_data = array();

			// Find the repeater2Id for this field
			$repeater2Id = null;
			if ( isset( $_POST['input_' . $field_id] ) ) {
				$metadata = json_decode( stripslashes( $_POST['input_' . $field_id] ), true );
				if ( isset( $metadata['repeater2Id'] ) ) {
					$repeater2Id = $metadata['repeater2Id'];
				}
			}

			if ( ! $repeater2Id ) {
				continue;
			}

			// Collect all renamed inputs for this repeater
			foreach ( $_POST as $input_name => $input_value ) {
				// Match pattern: input_{childId}-{repeaterId}-{iteration}
				// Note: childId can contain dots (Address: 173.3) or underscores (Name: 5_3)
				if ( preg_match( '/^input_([\d_.]+)-' . $repeater2Id . '-(\d+)$/', $input_name, $matches ) ) {
					$child_field_id = $matches[1];
					$iteration = $matches[2];

					// Extract base field ID and sub-input ID
					// e.g., "172_3" -> base="172", sub="3"; "174" -> base="174", sub=null
					$parts = preg_split( '/[_.]/', $child_field_id );
					$base_field_id = $parts[0];
					$sub_input_id = isset( $parts[1] ) ? $parts[1] : null;

					if ( ! isset( $iteration_data[ $iteration ] ) ) {
						$iteration_data[ $iteration ] = array();
					}

					if ( ! isset( $iteration_data[ $iteration ][ $base_field_id ] ) ) {
						$iteration_data[ $iteration ][ $base_field_id ] = array();
					}

					// Handle array values (like checkboxes) and single values
					if ( is_array( $input_value ) ) {
						$iteration_data[ $iteration ][ $base_field_id ] = array_merge( $iteration_data[ $iteration ][ $base_field_id ], $input_value );
					} else {
						// Store with sub-input ID as key if present, otherwise append
						if ( $sub_input_id !== null ) {
							$iteration_data[ $iteration ][ $base_field_id ][ $sub_input_id ] = $input_value;
						} else {
							$iteration_data[ $iteration ][ $base_field_id ][] = $input_value;
						}
					}
				}
			}

			// Store in transient for later retrieval during save
			if ( ! empty( $iteration_data ) ) {
				// Use resume token if available, otherwise use form ID (will be retrieved during save)
				$key_base = $resume_token ? $resume_token : 'form_' . $form['id'];
				$transient_key = 'gf_repeater_' . $key_base . '_field_' . $field_id;
				set_transient( $transient_key, $iteration_data, HOUR_IN_SECONDS );
			}
		}

		return $form;
	}

	public static function gform_modify_incomplete_submission_data( $submission, $resume_token, $form ) {
		// This hook fires BEFORE GF saves the incomplete submission
		// We retrieve repeater data from:
		// - Transients: for fields on previous pages (stored during page navigation)
		// - $_POST: for fields on the current page (not yet stored in transients)

		// Debug logging
		$debug_log = WP_CONTENT_DIR . '/repeater-debug.log';
		$debug_enabled = true;

		if ( $debug_enabled ) {
			file_put_contents( $debug_log, "\n\n=== " . date('Y-m-d H:i:s') . " gform_modify_incomplete_submission_data ===\n", FILE_APPEND );
			file_put_contents( $debug_log, "Form ID: " . $form['id'] . "\n", FILE_APPEND );
			file_put_contents( $debug_log, "Resume Token: " . $resume_token . "\n", FILE_APPEND );
			file_put_contents( $debug_log, "Submission type: " . gettype( $submission ) . "\n", FILE_APPEND );
		}

		// Decode submission if it's a JSON string
		$submission_array = is_string( $submission ) ? json_decode( $submission, true ) : $submission;

		// Ensure partial_entry exists
		if ( ! isset( $submission_array['partial_entry'] ) ) {
			$submission_array['partial_entry'] = array();
		}

		// Determine source page (the page we're currently on when saving)
		$source_page = isset( $_POST['gform_source_page_number_' . $form['id']] ) ? absint( $_POST['gform_source_page_number_' . $form['id']] ) : 1;

		if ( $debug_enabled ) {
			file_put_contents( $debug_log, "Source page: " . $source_page . "\n", FILE_APPEND );
		}

		// Try both resume_token and form_ID as keys (since page change might not have token yet)
		$key_base_token = $resume_token;
		$key_base_form = 'form_' . $form['id'];

		// Log all POST keys that look like repeater inputs
		if ( $debug_enabled ) {
			$repeater_post_keys = array();
			foreach ( $_POST as $key => $value ) {
				if ( strpos( $key, 'input_' ) === 0 ) {
					$repeater_post_keys[ $key ] = is_array( $value ) ? json_encode( $value ) : substr( $value, 0, 100 );
				}
			}
			file_put_contents( $debug_log, "POST input_* keys: " . print_r( $repeater_post_keys, true ) . "\n", FILE_APPEND );
		}

		foreach ( $form['fields'] as $field ) {
			if ( $field->type !== 'repeater2' ) {
				continue;
			}

			$field_id = $field->id;
			$field_page = isset( $field->pageNumber ) ? $field->pageNumber : 1;
			$iteration_data = null;

			if ( $debug_enabled ) {
				file_put_contents( $debug_log, "\nProcessing repeater field ID: " . $field_id . " (page " . $field_page . ")\n", FILE_APPEND );
			}

			// If field is on the current page, get data from $_POST
			// If field is on a previous page, get data from transient
			if ( $field_page == $source_page ) {
				// Current page field - get from $_POST
				$repeater2Id = null;
				$input_key = 'input_' . $field_id;

				if ( $debug_enabled ) {
					file_put_contents( $debug_log, "Looking for POST key: " . $input_key . "\n", FILE_APPEND );
					file_put_contents( $debug_log, "Key exists: " . ( isset( $_POST[ $input_key ] ) ? 'YES' : 'NO' ) . "\n", FILE_APPEND );
				}

				if ( isset( $_POST[ $input_key ] ) ) {
					$raw_value = $_POST[ $input_key ];
					if ( $debug_enabled ) {
						file_put_contents( $debug_log, "Raw value: " . substr( $raw_value, 0, 500 ) . "\n", FILE_APPEND );
					}

					$metadata = json_decode( stripslashes( $raw_value ), true );

					if ( $debug_enabled ) {
						file_put_contents( $debug_log, "Decoded metadata: " . print_r( $metadata, true ) . "\n", FILE_APPEND );
					}

					if ( isset( $metadata['repeater2Id'] ) ) {
						$repeater2Id = $metadata['repeater2Id'];
					}
				}

				if ( $debug_enabled ) {
					file_put_contents( $debug_log, "repeater2Id found: " . ( $repeater2Id ? $repeater2Id : 'NULL' ) . "\n", FILE_APPEND );
				}

				if ( $repeater2Id ) {
					$iteration_data = array();

					// Collect all renamed inputs for this repeater from $_POST
					// Note: childId can contain dots (Address: 173.3) or underscores (Name: 5_3)
					$matched_inputs = array();
					foreach ( $_POST as $input_name => $input_value ) {
						if ( preg_match( '/^input_([\d_.]+)-' . $repeater2Id . '-(\d+)$/', $input_name, $matches ) ) {
							$child_field_id = $matches[1];
							$iteration = $matches[2];

							$matched_inputs[] = $input_name;

							// Extract base field ID and sub-input ID
							// e.g., "172_3" -> base="172", sub="3"; "174" -> base="174", sub=null
							$parts = preg_split( '/[_.]/', $child_field_id );
							$base_field_id = $parts[0];
							$sub_input_id = isset( $parts[1] ) ? $parts[1] : null;

							if ( ! isset( $iteration_data[ $iteration ] ) ) {
								$iteration_data[ $iteration ] = array();
							}

							if ( ! isset( $iteration_data[ $iteration ][ $base_field_id ] ) ) {
								$iteration_data[ $iteration ][ $base_field_id ] = array();
							}

							if ( is_array( $input_value ) ) {
								$iteration_data[ $iteration ][ $base_field_id ] = array_merge( $iteration_data[ $iteration ][ $base_field_id ], $input_value );
							} else {
								// Store with sub-input ID as key if present, otherwise append
								if ( $sub_input_id !== null ) {
									$iteration_data[ $iteration ][ $base_field_id ][ $sub_input_id ] = $input_value;
								} else {
									$iteration_data[ $iteration ][ $base_field_id ][] = $input_value;
								}
							}
						}
					}

					if ( $debug_enabled ) {
						file_put_contents( $debug_log, "Matched inputs: " . print_r( $matched_inputs, true ) . "\n", FILE_APPEND );
						file_put_contents( $debug_log, "Iteration data: " . print_r( $iteration_data, true ) . "\n", FILE_APPEND );
					}
				}
			} else {
				// Previous page field - get from transient
				$transient_key_token = 'gf_repeater_' . $key_base_token . '_field_' . $field_id;
				$transient_key_form = 'gf_repeater_' . $key_base_form . '_field_' . $field_id;

				if ( $debug_enabled ) {
					file_put_contents( $debug_log, "Looking for transient: " . $transient_key_token . " or " . $transient_key_form . "\n", FILE_APPEND );
				}

				$iteration_data = get_transient( $transient_key_token );
				if ( ! $iteration_data ) {
					$iteration_data = get_transient( $transient_key_form );
				}

				if ( $debug_enabled ) {
					file_put_contents( $debug_log, "Transient data: " . print_r( $iteration_data, true ) . "\n", FILE_APPEND );
				}
			}

			// Save the data if we found any
			if ( ! empty( $iteration_data ) ) {
				$json_value = json_encode( $iteration_data );
				$submission_array['partial_entry'][ $field_id ] = $json_value;

				if ( $debug_enabled ) {
					file_put_contents( $debug_log, "SAVED to partial_entry[" . $field_id . "]: " . $json_value . "\n", FILE_APPEND );
				}
			} else {
				if ( $debug_enabled ) {
					file_put_contents( $debug_log, "NO DATA to save for field " . $field_id . "\n", FILE_APPEND );
				}
			}
		}

		if ( $debug_enabled ) {
			file_put_contents( $debug_log, "\nFinal submission_array partial_entry: " . print_r( $submission_array['partial_entry'], true ) . "\n", FILE_APPEND );
		}

		// Return in the same format as received (string or array)
		return is_string( $submission ) ? json_encode( $submission_array ) : $submission_array;
	}

	public static function gform_save_repeater_field_value( $value, $lead, $field, $form, $input_id ) {
		// This hook fires when GF is saving individual field values
		// Note: This hook only runs for fields on the current page

		if ( $field->type !== 'repeater2' ) {
			return $value;
		}

		// Find the repeater2Id for this field
		$repeater2Id = null;
		if ( isset( $_POST['input_' . $field->id] ) ) {
			$metadata = json_decode( stripslashes( $_POST['input_' . $field->id] ), true );
			if ( isset( $metadata['repeater2Id'] ) ) {
				$repeater2Id = $metadata['repeater2Id'];
			}
		}

		if ( ! $repeater2Id ) {
			return $value;
		}

		// Collect all renamed inputs for this repeater
		// Note: childId can contain dots (Address: 173.3) or underscores (Name: 5_3)
		$iteration_data = array();
		foreach ( $_POST as $input_name => $input_value ) {
			// Match pattern: input_{childId}-{repeaterId}-{iteration}
			if ( preg_match( '/^input_([\d_.]+)-' . $repeater2Id . '-(\d+)$/', $input_name, $matches ) ) {
				$child_field_id = $matches[1];
				$iteration = $matches[2];

				// Extract base field ID and sub-input ID
				// e.g., "172_3" -> base="172", sub="3"; "174" -> base="174", sub=null
				$parts = preg_split( '/[_.]/', $child_field_id );
				$base_field_id = $parts[0];
				$sub_input_id = isset( $parts[1] ) ? $parts[1] : null;

				if ( ! isset( $iteration_data[ $iteration ] ) ) {
					$iteration_data[ $iteration ] = array();
				}

				if ( ! isset( $iteration_data[ $iteration ][ $base_field_id ] ) ) {
					$iteration_data[ $iteration ][ $base_field_id ] = array();
				}

				// Handle array values (like checkboxes) and single values
				if ( is_array( $input_value ) ) {
					$iteration_data[ $iteration ][ $base_field_id ] = array_merge( $iteration_data[ $iteration ][ $base_field_id ], $input_value );
				} else {
					// Store with sub-input ID as key if present, otherwise append
					if ( $sub_input_id !== null ) {
						$iteration_data[ $iteration ][ $base_field_id ][ $sub_input_id ] = $input_value;
					} else {
						$iteration_data[ $iteration ][ $base_field_id ][] = $input_value;
					}
				}
			}
		}

		if ( ! empty( $iteration_data ) ) {
			return json_encode( $iteration_data );
		}

		return $value;
	}

	public static function gform_save_incomplete_submission( $submission, $resume_token, $form, $entry ) {
		// This hook fires AFTER GF saves the incomplete submission
		// We capture renamed inputs and save them to the entry for the current page

		foreach ( $form['fields'] as $field ) {
			if ( $field->type !== 'repeater2' ) {
				continue;
			}

			$field_id = $field->id;
			$iteration_data = array();

			// Find the repeater2Id for this field
			$repeater2Id = null;
			if ( isset( $_POST['input_' . $field_id] ) ) {
				$metadata = json_decode( stripslashes( $_POST['input_' . $field_id] ), true );
				if ( isset( $metadata['repeater2Id'] ) ) {
					$repeater2Id = $metadata['repeater2Id'];
				}
			}

			if ( ! $repeater2Id ) {
				continue;
			}

			// Collect all renamed inputs for this repeater
			foreach ( $_POST as $input_name => $input_value ) {
				// Match pattern: input_{childId}-{repeaterId}-{iteration}
				// Note: childId can contain dots (Address: 173.3) or underscores (Name: 5_3)
				if ( preg_match( '/^input_([\d_.]+)-' . $repeater2Id . '-(\d+)$/', $input_name, $matches ) ) {
					$child_field_id = $matches[1];
					$iteration = $matches[2];

					// Extract base field ID and sub-input ID
					// e.g., "172_3" -> base="172", sub="3"; "174" -> base="174", sub=null
					$parts = preg_split( '/[_.]/', $child_field_id );
					$base_field_id = $parts[0];
					$sub_input_id = isset( $parts[1] ) ? $parts[1] : null;

					if ( ! isset( $iteration_data[ $iteration ] ) ) {
						$iteration_data[ $iteration ] = array();
					}

					if ( ! isset( $iteration_data[ $iteration ][ $base_field_id ] ) ) {
						$iteration_data[ $iteration ][ $base_field_id ] = array();
					}

					// Handle array values (like checkboxes) and single values
					if ( is_array( $input_value ) ) {
						$iteration_data[ $iteration ][ $base_field_id ] = array_merge( $iteration_data[ $iteration ][ $base_field_id ], $input_value );
					} else {
						// Store with sub-input ID as key if present, otherwise append
						if ( $sub_input_id !== null ) {
							$iteration_data[ $iteration ][ $base_field_id ][ $sub_input_id ] = $input_value;
						} else {
							$iteration_data[ $iteration ][ $base_field_id ][] = $input_value;
						}
					}
				}
			}

			// Save the iteration data to the entry
			if ( ! empty( $iteration_data ) ) {
				$entry[ $field_id ] = json_encode( $iteration_data );
			}
		}

		return $entry;
	}

	/**
	 * Restore repeater field data for incomplete submissions (Save and Continue)
	 * This populates the renamed child fields when a user resumes their submission
	 *
	 * @param string $submission_json The submission JSON data
	 * @param string $resume_token The unique token for resuming
	 * @param array $form The form object
	 * @return string Modified submission JSON with repeater data
	 */
	public static function gform_restore_incomplete_submission( $submission_json, $resume_token, $form ) {

		// Debug logging
		$debug_log = WP_CONTENT_DIR . '/repeater-debug.log';
		$debug_enabled = true;

		if ( $debug_enabled ) {
			file_put_contents( $debug_log, "\n\n=== " . date('Y-m-d H:i:s') . " gform_restore_incomplete_submission ===\n", FILE_APPEND );
			file_put_contents( $debug_log, "Form ID: " . $form['id'] . "\n", FILE_APPEND );
			file_put_contents( $debug_log, "Resume Token: " . $resume_token . "\n", FILE_APPEND );
		}

		$submission = json_decode( $submission_json );

		if ( ! $submission || ! isset( $submission->partial_entry ) ) {
			if ( $debug_enabled ) {
				file_put_contents( $debug_log, "No submission or partial_entry found - returning early\n", FILE_APPEND );
			}
			return $submission_json;
		}

		$partial_entry = $submission->partial_entry;

		if ( $debug_enabled ) {
			file_put_contents( $debug_log, "Partial entry keys: " . print_r( array_keys( (array) $partial_entry ), true ) . "\n", FILE_APPEND );
		}

		// Loop through form fields to find repeater fields
		// repeater2Id is a sequential counter for each repeater field (1, 2, 3...)
		$repeater2Id = 0;

		foreach ( $form['fields'] as $field ) {
			if ( $field->type !== 'repeater2' ) {
				continue;
			}

			// Increment the repeater2Id for each repeater field we process
			$repeater2Id++;

			$field_id = $field->id;

			if ( $debug_enabled ) {
				file_put_contents( $debug_log, "\nProcessing repeater field ID: " . $field_id . " (repeater2Id: " . $repeater2Id . ")\n", FILE_APPEND );
			}

			// Get the saved repeater data from the partial entry
			// Try both object property access and array-style access
			$saved_data = null;
			if ( isset( $partial_entry->{$field_id} ) ) {
				$saved_data = $partial_entry->{$field_id};
			} elseif ( isset( $partial_entry->{"$field_id"} ) ) {
				$saved_data = $partial_entry->{"$field_id"};
			}

			if ( empty( $saved_data ) ) {
				if ( $debug_enabled ) {
					file_put_contents( $debug_log, "No saved data found for field " . $field_id . "\n", FILE_APPEND );
				}
				continue;
			}

			if ( $debug_enabled ) {
				file_put_contents( $debug_log, "Saved data found: " . substr( $saved_data, 0, 200 ) . "...\n", FILE_APPEND );
			}

			// Decode the repeater data
			$dataArray = json_decode( $saved_data, true );

			if ( ! $dataArray || ! is_array( $dataArray ) ) {
				continue;
			}

			// Build the children metadata with prePopulate data for the JavaScript
			$children_meta = array();

			// First pass: build the children structure
			foreach ( $dataArray as $iteration => $childValues ) {
				foreach ( $childValues as $child_field_id => $inputData ) {
					if ( ! isset( $children_meta[ $child_field_id ] ) ) {
						$children_meta[ $child_field_id ] = array( 'prePopulate' => array() );
					}

					// Store the data for this iteration
					if ( is_array( $inputData ) ) {
						// Check if this is the new format with sub-input IDs as keys (non-numeric keys like '3', '6')
						$keys = array_keys( $inputData );
						$has_sub_input_keys = ! empty( $keys ) && ! is_int( $keys[0] ) && is_numeric( $keys[0] );

						if ( $has_sub_input_keys ) {
							// New format: sub-input IDs as keys (e.g., ['3' => 'John', '6' => 'Doe'])
							foreach ( $inputData as $sub_input_id => $value ) {
								if ( ! isset( $children_meta[ $child_field_id ]['prePopulate'][ $sub_input_id ] ) ) {
									$children_meta[ $child_field_id ]['prePopulate'][ $sub_input_id ] = array();
								}
								if ( $value !== '' ) {
									$children_meta[ $child_field_id ]['prePopulate'][ $sub_input_id ][ $iteration ] = $value;
								}
							}
						} elseif ( count( $inputData ) === 1 ) {
							// Single-value field (old format or simple field)
							$children_meta[ $child_field_id ]['prePopulate'][ $iteration ] = reset( $inputData );
						} elseif ( count( $inputData ) > 1 ) {
							// Old format: Multi-input field with numeric indices - store by sub-input ID via field lookup
							$child_field_index = GF_Field_Repeater2::get_field_index( $form, 'id', $child_field_id );
							if ( $child_field_index !== false ) {
								$child_field = $form['fields'][ $child_field_index ];
								if ( isset( $child_field->inputs ) && is_array( $child_field->inputs ) ) {
									foreach ( $child_field->inputs as $input_index => $input ) {
										$sub_input_id = str_replace( $child_field_id . '.', '', $input['id'] );
										if ( ! isset( $children_meta[ $child_field_id ]['prePopulate'][ $sub_input_id ] ) ) {
											$children_meta[ $child_field_id ]['prePopulate'][ $sub_input_id ] = array();
										}
										if ( isset( $inputData[ $input_index ] ) && $inputData[ $input_index ] !== '' ) {
											$children_meta[ $child_field_id ]['prePopulate'][ $sub_input_id ][ $iteration ] = $inputData[ $input_index ];
										}
									}
								}
							}
						}
					} elseif ( $inputData !== '[gfRepeater-section]' && $inputData !== '' ) {
						// Non-array value
						$children_meta[ $child_field_id ]['prePopulate'][ $iteration ] = $inputData;
					}
				}
			}

			// Create the repeater metadata for submitted_values
			$input_name = 'input_' . $field_id;
			$repeater_meta = array(
				'repeater2Id' => $repeater2Id,
				'formId' => $form['id'],
				'start' => count( $dataArray ),
				'min' => 1,
				'max' => null,
				'children' => $children_meta
			);
			$metadata_json = json_encode( $repeater_meta );
			$submission->submitted_values->{$input_name} = $metadata_json;

			// Second pass: populate the submitted_values with the child field data using renamed keys
			foreach ( $dataArray as $iteration => $childValues ) {
				foreach ( $childValues as $child_field_id => $inputData ) {
					// Build the renamed input key
					$renamed_input = 'input_' . $child_field_id . '-' . $repeater2Id . '-' . $iteration;

					// Set the value in submitted_values
					if ( is_array( $inputData ) ) {
						// Check if this is the new format with sub-input IDs as keys (non-numeric keys like '3', '6')
						$keys = array_keys( $inputData );
						$has_sub_input_keys = ! empty( $keys ) && ! is_int( $keys[0] ) && is_numeric( $keys[0] );

						if ( $has_sub_input_keys ) {
							// New format: sub-input IDs as keys - use the sub-input ID directly
							foreach ( $inputData as $sub_input_id => $value ) {
								// Use dot notation to match HTML input names (input_172.3-8-1)
								$renamed_sub_input = 'input_' . $child_field_id . '.' . $sub_input_id . '-' . $repeater2Id . '-' . $iteration;
								$submission->submitted_values->{$renamed_sub_input} = $value;
							}
						} elseif ( count( $inputData ) === 1 ) {
							// Single-value field
							$submission->submitted_values->{$renamed_input} = reset( $inputData );
						} elseif ( count( $inputData ) > 1 ) {
							// Old format: Multi-value field with numeric indices - set each sub-input via field lookup
							$child_field_index = GF_Field_Repeater2::get_field_index( $form, 'id', $child_field_id );
							if ( $child_field_index !== false ) {
								$child_field = $form['fields'][ $child_field_index ];
								if ( isset( $child_field->inputs ) && is_array( $child_field->inputs ) ) {
									// This is a multi-input field like name or address
									foreach ( $child_field->inputs as $input_index => $input ) {
										// Use dot notation to match HTML input names (input_172.3-8-1)
										$renamed_sub_input = 'input_' . $input['id'] . '-' . $repeater2Id . '-' . $iteration;
										if ( isset( $inputData[ $input_index ] ) ) {
											$submission->submitted_values->{$renamed_sub_input} = $inputData[ $input_index ];
										}
									}
								}
							}
						}
					} elseif ( $inputData !== '[gfRepeater-section]' ) {
						$submission->submitted_values->{$renamed_input} = $inputData;
					}
				}
			}

			if ( $debug_enabled ) {
				file_put_contents( $debug_log, "Restored metadata for field " . $field_id . ": " . substr( $metadata_json, 0, 300 ) . "...\n", FILE_APPEND );
			}
		}

		if ( $debug_enabled ) {
			$submitted_values_keys = isset( $submission->submitted_values ) ? array_keys( (array) $submission->submitted_values ) : array();
			$repeater_keys = array_filter( $submitted_values_keys, function( $key ) {
				return strpos( $key, 'input_' ) === 0 && preg_match( '/-\d+-\d+$/', $key );
			});
			file_put_contents( $debug_log, "\nRestored submitted_values repeater keys: " . print_r( array_slice( $repeater_keys, 0, 20 ), true ) . "\n", FILE_APPEND );
		}

		return json_encode( $submission );
	}
}
GF_Fields::register(new GF_Field_Repeater2());
