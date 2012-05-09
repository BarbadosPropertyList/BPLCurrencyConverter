jQuery(document).ready(function($) {
	
	jQuery(".bplc_submit").click(function(event){ // function launched when submiting form
		
		event.preventDefault(); //disable default behavior
		
		var parent_element = jQuery(this).parent('.bplc_calculator');
		
		parent_element.find('input[name="bplc_result"]').val('Loading...');
		
		var data = { //looks for and sets all variables used for export
			action: 'bplc_count_ajax',
			amount: parent_element.find('input[name="bplc_amount"]').val(),
			from: parent_element.find('select[name="bplc_from"]').val(),
			to: parent_element.find('select[name="bplc_to"]').val()
		};
		
		jQuery.post(bplc_add_js.ajax_url, data, function(data){ //post data to specified action trough special WP ajax page
			parent_element.find('input[name="bplc_result"]').val(data);
		});

	});
	
	jQuery('.form_numbers').keyup(function () { 
		this.value = this.value.replace(/[^0-9\.]/g,'');
	});


});