<?php
/*
Plugin Name: BPL Currency Converter
Plugin URI:
Description: BPL Currency Converter
Version: 0.11
Author: BarbadosPropertyList
Author URI:
*/

register_activation_hook(__FILE__, 'bplc_activation');
add_action('bplc_currencies_cron_hook', 'bplc_update_currencies');

function bplc_activation() {
	wp_schedule_event( current_time( 'timestamp' ), 'twicedaily', 'bplc_currencies_cron_hook');
}

register_deactivation_hook(__FILE__, 'bplc_deactivation');
function bplc_deactivation() {
	wp_clear_scheduled_hook('bplc_currencies_cron_hook');
	delete_option( 'bplc_currencies' );
}

function bplc_update_currencies() {
	// Requested file
	// Could also be e.g. '/historical/2011-01-01.json' or 'currencies.json'
	$filename = 'latest.json';

	// Open CURL session:
	$ch = curl_init('http://openexchangerates.org/' . $filename . '?app_id=16df4175a3e74179b3f1fe0c8f7e5cc9');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	// Get the data:
	$json = curl_exec($ch);
	curl_close($ch);

	// Decode JSON response:
	$exchangeRates = json_decode($json);
	$currencies['latest_update'] = $exchangeRates->timestamp;
	foreach ( $exchangeRates->rates as $rate_name => $rate_value ) {
		$currencies[$rate_name]['value'] = $rate_value;
	}
	// Requested file
	// Could also be e.g. '/historical/2011-01-01.json' or 'currencies.json'
	$filename = 'currencies.json';

	// Open CURL session:
	$ch = curl_init('http://openexchangerates.org/' . $filename);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	// Get the data:
	$json = curl_exec($ch);
	curl_close($ch);

	// Decode JSON response:
	$exchangeNames = json_decode($json);
	foreach ( $exchangeNames as $rate_name => $rate_full_name ) {
		$currencies[$rate_name]['full_name'] = $rate_full_name;
	}

	//stores values into WP database

	if( get_option( 'bplc_currencies' ) === false && !empty($currencies) ) {
		add_option( 'bplc_currencies', $currencies );
	}
	elseif( !empty($currencies) ) {
		update_option( 'bplc_currencies', $currencies );
	}

}

//Loads Javascript for users so AJAX magic can happen
add_action( 'template_redirect', 'bplc_add_js' );
function bplc_add_js() {
    wp_enqueue_script( 'jquery' );

	wp_enqueue_script( 'bplc_add_js', plugins_url( 'bplc_converter.js' , __FILE__ ), array('jquery') );

	$protocol = isset( $_SERVER["HTTPS"] ) ? 'https://' : 'http://'; //This is used to set correct adress if secure protocol is used so ajax calls are working
	$params = array(
		'ajax_url' => admin_url( 'admin-ajax.php', $protocol )
	);
	wp_localize_script( 'bplc_add_js', 'bplc_add_js', $params );
}

// Shortcode magic:) sample usage:
// [bpl_currecy_converter amount="200" from="BBD" to="USD" show_time="no"].
// Defaults same as bplc_calculator_view function
add_shortcode( 'bpl_currecy_converter', 'bplc_calculator_shortcode_view' );
function bplc_calculator_shortcode_view($options) {
	if($options['show_time'] == no ) {
		 $options['show_time'] = 0;
	} else {
		$options['show_time'] = 1;
	}

	$return = '<div class="bpl_currency_converter_shortcode">';
	$return .= bplc_calculator_view( $options['amount'], $options['from'], $options['to'], $options['show_time'], 0 );
	$return .= '</div>';
	
	return $return;
	
}

//function that displays and does all the processing
function bplc_calculator_view( $amount = '100', $from = 'USD', $to='BBD', $time = 1, $echo = 1 ) {
	echo $return;
	//sets up defaults
	if(empty($amount)) $amount=100;
	if(empty($from)) $from = 'USD';
	if(empty($to)) $to = 'BBD';


	//sets up/gets values for later use
	$currencies = get_option( 'bplc_currencies' );
	$update_time = $currencies['latest_update'];
	unset($currencies['latest_update']);
	
	$currencies_select_options_from = bplc_select_options($currencies, $from);
	$currencies_select_options_to = bplc_select_options($currencies, $to);
	

	
	$return = '
	<div class="bplc_calculator_holder">
		<form method="post" action="" class="bplc_calculator" name="bplc_calculator">
			<p class="bplc_amount_holder">
				<label class="bplc_amount_label" for="bplc_amount">'.__('Amount to convert:', 'bplc_plugin').'</label>
				<input class="bplc_amount form_numbers" name="bplc_amount" value="'.$amount.'" />
			</p>

			<p class="bplc_from_holder">
				<label class="bplc_from_label" for="bplc_from">'.__('From:', 'bplc_plugin').'</label>
				<select class="bplc_from" name="bplc_from" >
					'.$currencies_select_options_from.'
				</select>
			</p>
			<p class="bplc_to_holder">
				<label class="bplc_to_label" for="bplc_to">'.__('To:', 'bplc_plugin').'</label>
				<select class="bplc_to" name="bplc_to" >
					'.$currencies_select_options_to.'
				</select>
			</p>
			<p  class="bplc_result_holder">
				<label class="bplc_result_label" for="bplc_result">'.__('Result:', 'bplc_plugin').'</label>
				<input readonly="readonly" class="bplc_result" name="bplc_result" value="" />
			</p>
			<input class="bplc_submit" type="submit" value="Calculate" style="width: 200px; height: 35px;"/>
	';
	if(!empty($time)) {
	$return .= '	
			<p style="margin-top: 20px;"><span class="bplc_credits_holder">
				'.__('Rates from:', 'bplc_plugin').' '.date('h:i jS F, Y',$update_time).'<br/><a href="http://www.barbadospropertylist.com/">Built by <img alt="Barbados Property List" align="middle" src="'.plugins_url( 'bplc_logo.png' , __FILE__ ).'" style="vertical-align: text-bottom;" /></a>
			</span></p>
	';
	}
	$return .= '
		</form>

	</div>
	';
	
	if($echo != 1) {
		return $return;
	}
	else {
		echo $return;
	}
	
}
//helpers
function bplc_select_options($data, $correct) {
	$currencies_select_options = '';

	foreach ( $data as $currency_name => $currency_details ) {

		$currencies_select_options .= '
		<option value="'.$currency_name.'"';
			if ( $currency_name == $correct ) $currencies_select_options .= ' selected="selected"';
			$currencies_select_options .= '>';
		if( !empty($currency_details['full_name']) ){
			$currencies_select_options .= $currency_details['full_name'];
		} else {
			$currencies_select_options .= $currency_name;
		}
		$currencies_select_options .= '</option>';
		
	}
	
	return 	$currencies_select_options;
}

//handle all the AJAX counting
add_action( 'wp_ajax_bplc_count_ajax', 'bplc_count_ajax' );
add_action('wp_ajax_nopriv_bplc_count_ajax', 'bplc_count_ajax');
function bplc_count_ajax() {

	$currencies = get_option( 'bplc_currencies' );

	if( isset($currencies) && !empty($_POST['amount']) && !empty($_POST['from']) && !empty($_POST['to'])) {
		if(is_numeric($_POST['amount'])) {
			$amount = $_POST['amount'];
		}

		$from_rate = $currencies[$_POST['from']]['value'];
		$to_rate = $currencies[$_POST['to']]['value'];
	}

	if( !empty($amount) && !empty($from_rate) && !empty($to_rate) ) {
		$result = $amount/$from_rate*$to_rate;
	}

	if(isset($result)) {
		echo number_format($result , 2, '.', ',');
	}
	else{
		echo 'Error';
	}

	die();

}

//Creates widget for BPL Converter
add_action( 'widgets_init', 'bplc_load_widget' );
function bplc_load_widget() {
	register_widget( 'bplc_widget' );
}

class bplc_widget extends WP_Widget {

	/**
	 * Widget setup.
	 */
	function bplc_widget() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'bplc_widget', 'description' => __('Barbados Property List Currency Converter.', 'bplc_widget') );

		/* Widget control settings. */
		$control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'bplc-widget' );

		/* Create the widget. */
		$this->WP_Widget( 'bplc-widget', __('BPL Currency Converter', 'bplc_widget'), $widget_ops, $control_ops );
	}

	/**
	 * How to display the widget on the screen.
	 */
	function widget( $args, $instance ) {
		extract( $args );

		/* Our variables from the widget settings. */
		$title = apply_filters('widget_title', $instance['title'] );
		$amount = $instance['amount'];
		$from = $instance['from'];
		$to = $instance['to'];
		$time = isset( $instance['time'] ) ? $instance['time'] : false;

		/* Before widget (defined by themes). */
		echo $before_widget;

		/* Display the widget title if one was input (before and after defined by themes). */
		if ( $title )
			echo $before_title . $title . $after_title;

		//Function that is doing all the magic
		bplc_calculator_view($amount, $from, $to, $time );

		/* After widget (defined by themes). */
		echo $after_widget;
	}

	/**
	 * Update the widget settings.
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags for title and name to remove HTML (important for text inputs). */
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['amount'] = strip_tags( $new_instance['amount'] );

		/* No need to strip this tags. */
		$instance['from'] = $new_instance['from'];
		$instance['to'] = $new_instance['to'];
		$instance['time'] = $new_instance['time'];

		return $instance;
	}

	/**
	 * Displays the widget settings controls on the widget panel.
	 * Make use of the get_field_id() and get_field_name() function
	 * when creating your form elements. This handles the confusing stuff.
	 */
	function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array( 'title' => __('BPL Currency Converter', 'bplc_plugin'), 'amount' => '100', 'from' => 'USD', 'to' => 'BBD', 'time' => true );
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>

		<!-- Widget Title: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Widget Title:', 'bplc_plugin'); ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
		</p>

		<!-- Default Amount: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'amount' ); ?>"><?php _e('Default Amount:', 'bplc_plugin'); ?></label>
			<input id="<?php echo $this->get_field_id( 'amount' ); ?>" name="<?php echo $this->get_field_name( 'amount' ); ?>" value="<?php echo $instance['amount']; ?>" style="width:100%;" />
		</p>


		<?php
		$currencies = get_option( 'bplc_currencies' );
		unset($currencies['latest_update']);
		?>
		<!-- Default From Currency: Select Box -->
		<p>
			<label for="<?php echo $this->get_field_id( 'from' ); ?>"><?php _e('Default From Currency:', 'bplc_plugin'); ?></label>
			<select id="<?php echo $this->get_field_id( 'from' ); ?>" name="<?php echo $this->get_field_name( 'from' ); ?>" class="widefat" style="width:100%;">
				<?php
				foreach ( $currencies as $currency_name => $currency_details ) {
				?>
					<option value="<?php echo $currency_name; ?>" <?php if ( $currency_name == $instance['from'] ) echo 'selected="selected"'; ?>><?php if( !empty($currency_details['full_name']) ){ echo $currency_details['full_name']; } else { echo $currency_name; }?></option>
				<?php
				}
				?>
			</select>
		</p>

		<!-- Default To Currency: Select Box -->
		<p>
			<label for="<?php echo $this->get_field_id( 'to' ); ?>"><?php _e('Default To Currency:', 'bplc_plugin'); ?></label>
			<select id="<?php echo $this->get_field_id( 'to' ); ?>" name="<?php echo $this->get_field_name( 'to' ); ?>" class="widefat" style="width:100%;">
				<?php
				foreach ( $currencies as $currency_name => $currency_details ) {
				?>
					<option value="<?php echo $currency_name; ?>" <?php if ( $currency_name == $instance['to'] ) echo 'selected="selected"'; ?>><?php if( !empty($currency_details['full_name']) ){ echo $currency_details['full_name']; } else { echo $currency_name; }?></option>
				<?php
				}
				?>
			</select>
		</p>

		<!-- Show latest update time? Checkbox -->
		<p>
			<input class="checkbox" type="checkbox" value="1"<?php checked( $instance['time'], 1 ); ?> id="<?php echo $this->get_field_id( 'time' ); ?>" name="<?php echo $this->get_field_name( 'time' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'time' ); ?>"><?php _e('Show Latest Update Time?', 'bplc_plugin');?></label>
		</p>

	<?php
	}
}
?>
