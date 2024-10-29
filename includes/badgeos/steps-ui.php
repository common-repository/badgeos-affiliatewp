<?php
/**
 * Custom Achievement Steps UI.
 *
 * @package BadgeOS AffiliateWP
 * @subpackage Achievements
 * @author Credly, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Update badgeos_get_step_requirements to include our custom requirements.
 *
 * @param $requirements
 * @param $step_id
 * @return mixed
 */
function badgeos_affiliatewp_step_requirements( $requirements, $step_id ) {

	/**
     * Add our new requirements to the list
     */
	$requirements[ 'affiliatewp_trigger' ] = get_post_meta( $step_id, '_badgeos_affiliatewp_trigger', true );
	$requirements[ 'affiliatewp_object_id' ] = (int) get_post_meta( $step_id, '_badgeos_affiliatewp_object_id', true );
	$requirements[ 'affiliatewp_object_arg1' ] = (int) get_post_meta( $step_id, '_badgeos_affiliatewp_object_arg1', true );

	return $requirements;
}
add_filter( 'badgeos_get_step_requirements', 'badgeos_affiliatewp_step_requirements', 10, 2 );
add_filter( 'badgeos_get_rank_req_step_requirements', 'badgeos_affiliatewp_step_requirements', 10, 2 );
add_filter( 'badgeos_get_award_step_requirements', 'badgeos_affiliatewp_step_requirements', 10, 2 );
add_filter( 'badgeos_get_deduct_step_requirements', 'badgeos_affiliatewp_step_requirements', 10, 2 );

/**
 * Filter the BadgeOS Triggers selector with our own options.
 *
 * @param $triggers
 * @return mixed
 */
function badgeos_affiliatewp_activity_triggers( $triggers ) {
	$triggers[ 'affiliatewp_trigger' ] = __( 'AffiliateWP Activity', 'bos-awp' );

	return $triggers;
}
add_filter( 'badgeos_activity_triggers', 'badgeos_affiliatewp_activity_triggers' );
add_filter( 'badgeos_ranks_req_activity_triggers', 'badgeos_affiliatewp_activity_triggers' );
add_filter( 'badgeos_award_points_activity_triggers', 'badgeos_affiliatewp_activity_triggers' );
add_filter( 'badgeos_deduct_points_activity_triggers', 'badgeos_affiliatewp_activity_triggers' );

/**
 * Add AffiliateWP Triggers selector to the Steps UI.
 *
 * @param $step_id
 * @param $post_id
 */
function badgeos_affiliatewp_step_affiliatewp_trigger_select( $step_id, $post_id ) {

	/**
     * Setup our select input
     */
	echo '<select name="affiliatewp_trigger" class="select-affiliatewp-trigger">';
	echo '<option value="">' . __( 'Select a AffiliateWP Trigger', 'bos-awp' ) . '</option>';

	/**
     * Loop through all of our AffiliateWP trigger groups
     */
	$current_trigger = get_post_meta( $step_id, '_badgeos_affiliatewp_trigger', true );

	$affiliatewp_triggers = $GLOBALS[ 'badgeos_affiliatewp' ]->triggers;

	if ( !empty( $affiliatewp_triggers ) ) {
		foreach ( $affiliatewp_triggers as $trigger => $trigger_label ) {
			if ( is_array( $trigger_label ) ) {
				$optgroup_name = $trigger;
				$triggers = $trigger_label;

				echo '<optgroup label="' . esc_attr( $optgroup_name ) . '">';

				/**
                 * Loop through each trigger in the group
                 */
				foreach ( $triggers as $trigger_hook => $trigger_name ) {
					echo '<option' . selected( $current_trigger, $trigger_hook, false ) . ' value="' . esc_attr( $trigger_hook ) . '">' . esc_html( $trigger_name ) . '</option>';
				}
				echo '</optgroup>';
			} else {
				echo '<option' . selected( $current_trigger, $trigger, false ) . ' value="' . esc_attr( $trigger ) . '">' . esc_html( $trigger_label ) . '</option>';
			}
		}
	}

	echo '</select>';

}
add_action( 'badgeos_steps_ui_html_after_trigger_type', 'badgeos_affiliatewp_step_affiliatewp_trigger_select', 10, 2 );
add_action( 'badgeos_rank_req_steps_ui_html_after_trigger_type', 'badgeos_affiliatewp_step_affiliatewp_trigger_select', 10, 2 );
add_action( 'badgeos_award_steps_ui_html_after_achievement_type', 'badgeos_affiliatewp_step_affiliatewp_trigger_select', 10, 2 );
add_action( 'badgeos_deduct_steps_ui_html_after_trigger_type', 'badgeos_affiliatewp_step_affiliatewp_trigger_select', 10, 2 );


/**
 * AJAX Handler for saving all steps.
 *
 * @param $title
 * @param $step_id
 * @param $step_data
 * @return string|void
 */
function badgeos_affiliatewp_save_step( $title, $step_id, $step_data ) {

	/**
     * If we're working on a AffiliateWP trigger
     */
	if ( 'affiliatewp_trigger' == $step_data[ 'trigger_type' ] ) {

		/**
         * Update our AffiliateWP trigger post meta
         */
		update_post_meta( $step_id, '_badgeos_affiliatewp_trigger', $step_data[ 'affiliatewp_trigger' ] );

		/**
         * Rewrite the step title
         */
		$title = $step_data[ 'affiliatewp_trigger_label' ];

        $object_id = 0;
        $object_arg1 = 0;

		/**
         * Store our Object ID in meta
         */
		update_post_meta( $step_id, '_badgeos_affiliatewp_object_id', $object_id );
		update_post_meta( $step_id, '_badgeos_affiliatewp_object_arg1', $object_arg1 );
	} else {
        delete_post_meta( $step_id, '_badgeos_affiliatewp_trigger' );
        delete_post_meta( $step_id, '_badgeos_affiliatewp_object_id' );
        delete_post_meta( $step_id, '_badgeos_affiliatewp_object_arg1' );
    }

	return $title;
}
add_filter( 'badgeos_save_step', 'badgeos_affiliatewp_save_step', 10, 3 );

/**
 * Include custom JS for the BadgeOS Steps UI.
 */
function badgeos_affiliatewp_step_js() {
	?>
	<script type="text/javascript">
		jQuery( document ).ready( function ( $ ) { 

			var times = $( '.required-count' ).val();

            /**
             * Listen for our change to our trigger type selector
             */
			$( document ).on( 'change', '.select-trigger-type', function () {

				var trigger_type = $( this ); 

                /**
                 * Show our group selector if we're awarding based on a specific group
                 */
				if ( 'affiliatewp_trigger' == trigger_type.val() ) {
					trigger_type.siblings( '.select-affiliatewp-trigger' ).show().change();
					var trigger = $('.select-affiliatewp-trigger').val();

				}  else {
					trigger_type.siblings( '.select-affiliatewp-trigger' ).val('').hide().change();
					$( '.input-quiz-grade' ).parent().hide();

					$( '.required-count' ).val( times );
				}
			} );

            /**
             * Listen for our change to our trigger type selector
             */
			$( document ).on( 'change', '.select-affiliatewp-trigger', function () {
				badgeos_affiliatewp_step_change( $( this ) , times);
			} );

            /**
             * Trigger a change so we properly show/hide our AffiliateWP menus
             */
			$( '.select-trigger-type' ).change();

            /**
             * Inject our custom step details into the update step action
             */
			$( document ).on( 'update_step_data', function ( event, step_details, step ) {
				step_details.affiliatewp_trigger = $( '.select-affiliatewp-trigger', step ).val();
				step_details.affiliatewp_trigger_label = $( '.select-affiliatewp-trigger option', step ).filter( ':selected' ).text();
			} );

		} );

		function badgeos_affiliatewp_step_change( $this , times) {

			var trigger_parent = $this.parent();
			var	trigger_parent_value = trigger_parent.find( '.select-trigger-type' ).val();

            if ( trigger_parent_value != 'affiliatewp_trigger' ) {

                trigger_parent.find('.required-count')
                    .val(times);
            }

		}
	</script>
<?php
}
add_action( 'admin_footer', 'badgeos_affiliatewp_step_js' );