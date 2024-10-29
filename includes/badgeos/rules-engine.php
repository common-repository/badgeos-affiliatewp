<?php
/**
 * Custom Rules
 *
 * @package BadgeOS AffiliateWP
 * @author WooNinjas
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://wooninjas.com
 */

/**
 * Load up our AffiliateWP triggers so we can add actions to them
 */
function badgeos_affiliatewp_load_triggers() {

    /**
     * Grab our AffiliateWP triggers
     */
    $affiliatewp_triggers = $GLOBALS[ 'badgeos_affiliatewp' ]->triggers;

    if ( !empty( $affiliatewp_triggers ) ) {
        foreach ( $affiliatewp_triggers as $trigger => $trigger_label ) {

            if ( is_array( $trigger_label ) ) {
                $triggers = $trigger_label;

                foreach ( $triggers as $trigger_hook => $trigger_name ) {
                    add_action( $trigger_hook, 'badgeos_affiliatewp_trigger_event', 0, 20 );
                }
            } else {
                add_action( $trigger, 'badgeos_affiliatewp_trigger_event', 0, 20 );
            }
        }
    }
}

add_action( 'init', 'badgeos_affiliatewp_load_triggers', 0 );

/**
 * Handle each of our AffiliateWP triggers
 */
function badgeos_affiliatewp_trigger_event() {

    /**
     * Setup all our important variables
     */
    global $blog_id, $wpdb;

    /**
     * Grab the current trigger
     */
    $this_trigger = current_filter();

    /**
     * Setup args
     */

    $args = func_get_args();

    if($this_trigger == 'affwp_insert_affiliate') {
        $affiliate_id = affiliate_wp()->tracking->get_affiliate_id();

        if(!empty($affiliate_id)) {
            $userID = affwp_get_affiliate_user_id($affiliate_id);
        }
        /*list($affiliate_id, $args1) = $args;
        $userID = $args1['user_id'];
        $affiliate_status = $args1['status'];*/
    } elseif($this_trigger == 'affwp_insert_referral') {
        list($referral_id) = $args;
        $referral = affiliate_wp()->referrals->get($referral_id);
        if( !empty($referral) ) {
            $affiliate = affiliate_wp()->affiliates->get($referral->affiliate_id);
            if( !empty($affiliate) ) {
                $userID = $affiliate->user_id;
            }
        }
    } elseif( in_array($this_trigger, array('badgeos_affwp_set_referral_status_paid','badgeos_affwp_set_referral_status_rejected')) ) {

        list($referral_id, $new_status, $old_status) = $args;

        if($this_trigger == 'badgeos_affwp_set_referral_status_paid') {

            if($new_status == 'paid' && $old_status != 'paid') {
                $referral = affiliate_wp()->referrals->get($referral_id);
                if (!empty($referral)) {
                    $affiliate = affiliate_wp()->affiliates->get($referral->affiliate_id);
                    if (!empty($affiliate)) {
                        $userID = $affiliate->user_id;
                    }
                }
            }
        } elseif($this_trigger == 'badgeos_affwp_set_referral_status_rejected') {

            if($new_status == 'rejected' && $old_status != 'rejected') {
                $referral = affiliate_wp()->referrals->get($referral_id);
                if (!empty($referral)) {
                    $affiliate = affiliate_wp()->affiliates->get($referral->affiliate_id);
                    if (!empty($affiliate)) {
                        $userID = $affiliate->user_id;
                    }
                }
            }
        }

    } elseif($this_trigger == 'affwp_post_insert_visit') {

        list($visit_id, $data) = $args;
        $affiliate = affiliate_wp()->affiliates->get($data['affiliate_id']);
        if (!empty($affiliate)) {
            $userID = $affiliate->user_id;
        }
    } elseif($this_trigger == 'user_register') {
        $affiliate_id = affiliate_wp()->tracking->get_affiliate_id();

        if(!empty($affiliate_id)) {
            $userID = affwp_get_affiliate_user_id($affiliate_id);
        }
    }


    if ( empty( $userID ) ) {
        return;
    }

    $user_data = get_user_by( 'id', $userID );

    if ( empty( $user_data ) ) {
        return;
    }

    /**
     * Now determine if any badges are earned based on this trigger event
     */

    $triggered_achievements = $wpdb->get_results( $wpdb->prepare( "SELECT pm.post_id, p.post_type FROM $wpdb->postmeta as pm inner join $wpdb->posts as p on( pm.post_id = p.ID ) WHERE p.post_status = 'publish' and pm.meta_key = '_badgeos_affiliatewp_trigger' AND pm.meta_value = %s", $this_trigger) );

    if( count( $triggered_achievements ) > 0 ) {
        /**
         * Update hook count for this user
         */
        $new_count = badgeos_update_user_trigger_count( $userID, $this_trigger, $blog_id );

        /**
         * Mark the count in the log entry
         */
        badgeos_post_log_entry( null, $userID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'bos-awp' ), $user_data->user_login, $this_trigger, $new_count ) );
    }

    foreach ( $triggered_achievements as $achievement ) {

        $parents = badgeos_get_achievements( array( 'parent_of' => $achievement->post_id ) );
        if( count( $parents ) > 0 ) {
            if( $parents[0]->post_status == 'publish' ) {
                badgeos_maybe_award_achievement_to_user( $achievement->post_id, $userID, $this_trigger, $blog_id, $args );
            }
        }

        //Rank
        $rank = $achievement;
        $parent_id = badgeos_get_parent_id( $rank->post_id );

        if( absint($parent_id) > 0) {
            $new_count = badgeos_ranks_update_user_trigger_count( $rank->post_id, $parent_id,$userID, $this_trigger, $blog_id, $args );
            badgeos_maybe_award_rank( $rank->post_id,$parent_id,$userID, $this_trigger, $blog_id, $args );
        }

        //Point
        $point = $achievement;
        $parent_id = badgeos_get_parent_id( $point->post_id );
        if( absint($parent_id) > 0) {
            if($point->post_type == 'point_award') {
                $new_count = badgeos_points_update_user_trigger_count($point->post_id, $parent_id, $userID, $this_trigger, $blog_id, 'Award', $args);
                badgeos_maybe_award_points_to_user($point->post_id, $parent_id, $userID, $this_trigger, $blog_id, $args);
            } else if($point->post_type == 'point_deduct') {
                $new_count = badgeos_points_update_user_trigger_count($point->post_id, $parent_id, $userID, $this_trigger, $blog_id, 'Deduct', $args);
                badgeos_maybe_deduct_points_to_user($point->post_id, $parent_id, $userID, $this_trigger, $blog_id, $args);
            }
        }

    }
}

/**
 * Check if user deserves a AffiliateWP trigger step
 *
 * @param $return
 * @param $user_id
 * @param $achievement_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_affiliatewp_user_deserves_affiliatewp_step( $return, $user_id, $achievement_id, $this_trigger = '', $site_id = 1, $args = array() ) {

    /**
     * If we're not dealing with a step, bail here
     */
    $post_type = get_post_type( $achievement_id );

    $bos_step_post_type = bos_affwp_get_post_type('step');

    if ( $bos_step_post_type !=  $post_type ) {

        //TODO: Investigate why below 3 types inserted in achievements table, when same trigger is assigned to achievements and points
        if( in_array( $post_type, array( 'point_deduct', 'point_award', 'point_type' ) ) ) {
            $return = false;
        }

        return $return;
    }

    /**
     * Grab our step requirements
     */
    $requirements = badgeos_get_step_requirements( $achievement_id );

    /**
     * If the step is triggered by AffiliateWP actions...
     */
    if ( 'affiliatewp_trigger' == $requirements[ 'trigger_type' ] ) {

        /**
         * Do not pass go until we say you can
         */
        $return = false;

        /**
         * Unsupported trigger
         */
        if ( ! isset( $GLOBALS[ 'badgeos_affiliatewp' ]->triggers[ $this_trigger ] ) ) {
            return $return;
        }

        $affiliatewp_triggered = is_affiliatewp_trigger($requirements, $args);

        /**
         * AffiliateWP requirements met
         */
        if ( $affiliatewp_triggered ) {

            $parent_achievement = badgeos_get_parent_of_achievement( $achievement_id );
            $parent_id = $parent_achievement->ID;

            $user_crossed_max_allowed_earnings = badgeos_achievement_user_exceeded_max_earnings( $user_id, $parent_id );
            if ( ! $user_crossed_max_allowed_earnings ) {
                $minimum_activity_count = absint( get_post_meta( $achievement_id, '_badgeos_count', true ) );
                if( ! isset( $minimum_activity_count ) || empty( $minimum_activity_count ) )
                    $minimum_activity_count = 1;

                $count_step_trigger = $requirements["affiliatewp_trigger"];
                $activities = badgeos_get_user_trigger_count( $user_id, $count_step_trigger );
                $relevant_count = absint( $activities );

                $achievements = badgeos_get_user_achievements(
                    array(
                        'user_id' => absint( $user_id ),
                        'achievement_id' => $achievement_id
                    )
                );

                $total_achievments = count( $achievements );
                $used_points = intval( $minimum_activity_count ) * intval( $total_achievments );
                $remainder = intval( $relevant_count ) - $used_points;

                if ( absint( $remainder ) >= $minimum_activity_count ) {
                    $return = true;
                }

            } else {

                $return = 0;
            }

        }
    }

    return $return;
}

add_filter( 'user_deserves_achievement', 'badgeos_affiliatewp_user_deserves_affiliatewp_step', 15, 6 );

function bos_affwp_badgeos_is_achievement_cb($return, $post) {

    $bos_step_post_type = bos_affwp_get_post_type('step');

    if( get_post_type($post) == $bos_step_post_type ) {
        $return = true;
    }

    return $return;
}

add_filter('badgeos_is_achievement', 'bos_affwp_badgeos_is_achievement_cb', 16, 2);

/**
 * Check if user does not have the same rank step already, and is eligible for the step
 *
 * @param $return_val
 * @param $step_id
 * @param $rank_id
 * @param $user_id
 * @param $this_trigger
 * @param $site_id
 * @param $args
 *
 * @return bool
 */
function badgeos_affiliatewp_user_deserves_rank_step($return_val, $step_id, $rank_id, $user_id, $this_trigger, $site_id, $args) {

    /**
     * If we're not dealing with a rank_requirement, bail here
     */

    $bos_rank_requirement_post_type = bos_affwp_get_post_type('rank_requirement');

    if ( $bos_rank_requirement_post_type != get_post_type( $step_id ) ) {
        return $return_val;
    }

    /**
     * Grab our step requirements
     */
    $requirements = badgeos_get_rank_req_step_requirements( $step_id );

    /**
     * If the step is triggered by AffiliateWP actions...
     */
    if ( 'affiliatewp_trigger' == $requirements[ 'trigger_type' ] ) {

        /**
         * Do not pass go until we say you can
         */
        $return_val = false;

        /**
         * Unsupported trigger
         */
        if ( ! isset( $GLOBALS[ 'badgeos_affiliatewp' ]->triggers[ $this_trigger ] ) ) {
            return $return_val;
        }

        $affiliatewp_triggered = is_affiliatewp_trigger($requirements, $args);

        /**
         * AffiliateWP requirements met
         */

        $return_val = $affiliatewp_triggered;

    }

    return $return_val;
}

add_filter('badgeos_user_deserves_rank_step', 'badgeos_affiliatewp_user_deserves_rank_step', 10, 7);

/**
 *
 * Check if user does not have the same rank already, and is eligible for the rank
 *
 * @param $completed
 * @param $step_id
 * @param $rank_id
 * @param $user_id
 * @param $this_trigger
 * @param $site_id
 * @param $args
 *
 * @return bool
 */
function badgeos_affiliatewp_user_deserves_rank_award($completed, $step_id, $rank_id, $user_id, $this_trigger, $site_id, $args) {

    /**
     * If we're not dealing with a rank_requirement, bail here
     */
    $bos_rank_requirement_post_type = bos_affwp_get_post_type('rank_requirement');
    if ( $bos_rank_requirement_post_type != get_post_type( $step_id ) ) {
        return $completed;
    }

    /**
     * Get the requirement rank
     */
    $rank = badgeos_get_rank_requirement_rank( $step_id );

    /**
     * Get all requirements of this rank
     */
    $requirements = badgeos_get_rank_requirements( $rank_id );

    $completed = true;

    foreach( $requirements as $requirement ) {

        /**
         * Check if rank requirement has been earned
         */
        if( ! badgeos_get_user_ranks( array(
            'user_id' => $user_id,
            'rank_id' => $requirement->ID,
            'since' => strtotime( $rank->post_date ),
            'no_steps' => false
        ) ) ) {
            $completed = false;
            break;
        }
    }

    return $completed;
}

add_filter( 'badgeos_user_deserves_rank_award', 'badgeos_affiliatewp_user_deserves_rank_award', 15, 7 );

/**
 *
 * Check if user is eligible for the points award
 *
 * @param $return_val
 * @param $step_id
 * @param $credit_parent_id
 * @param $user_id
 * @param $this_trigger
 * @param $site_id
 * @param $args
 *
 * @return bool
 */
function badgeos_affiliatewp_user_deserves_credit_award_cb ($return_val, $step_id, $credit_parent_id, $user_id, $this_trigger, $site_id, $args) {
    /**
     * If we're not dealing with correct requirement type, bail here
     */
    $bos_point_award_post_type = bos_affwp_get_post_type('point_award');
    if ( $bos_point_award_post_type != get_post_type( $step_id ) ) {
        return $return_val;
    }

    /**
     * Grab our step requirements
     */
    $requirements = badgeos_get_award_step_requirements( $step_id );

    /**
     * If the step is triggered by AffiliateWP actions...
     */
    if ( 'affiliatewp_trigger' == $requirements[ 'trigger_type' ] ) {

        /**
         * Do not pass go until we say you can
         */
        $return_val = false;

        /**
         * Unsupported trigger
         */
        if ( ! isset( $GLOBALS[ 'badgeos_affiliatewp' ]->triggers[ $this_trigger ] ) ) {
            return $return_val;
        }

        $affiliatewp_triggered = is_affiliatewp_trigger($requirements, $args);

        /**
         * AffiliateWP requirements met
         */

        $return_val = $affiliatewp_triggered;

    }

    return $return_val;
}

add_filter( 'badgeos_user_deserves_credit_award', 'badgeos_affiliatewp_user_deserves_credit_award_cb', 10, 7 );

/**
 * Check if user is eligible for the points deduction
 *
 * @param $return_val
 * @param $step_id
 * @param $credit_parent_id
 * @param $user_id
 * @param $this_trigger
 * @param $site_id
 * @param $args
 *
 * @return bool
 */
function badgeos_affiliatewp_user_deserves_credit_deduct_cb ($return_val, $step_id, $credit_parent_id, $user_id, $this_trigger, $site_id, $args) {

    /**
     * If we're not dealing with correct requirement type, bail here
     */
    $bos_point_deduct_post_type = bos_affwp_get_post_type('point_deduct');
    if ( $bos_point_deduct_post_type != get_post_type( $step_id ) ) {
        return $return_val;
    }

    /**
     * Grab our step requirements
     */
    $requirements = badgeos_get_deduct_step_requirements( $step_id );

    /**
     * If the step is triggered by AffiliateWP actions...
     */
    if ( 'affiliatewp_trigger' == $requirements[ 'trigger_type' ] ) {

        /**
         * Do not pass go until we say you can
         */
        $return_val = false;

        /**
         * Unsupported trigger
         */
        if ( ! isset( $GLOBALS[ 'badgeos_affiliatewp' ]->triggers[ $this_trigger ] ) ) {
            return $return_val;
        }

        $affiliatewp_triggered = is_affiliatewp_trigger($requirements, $args);

        /**
         * AffiliateWP requirements met
         */

        $return_val = $affiliatewp_triggered;

    }

    return $return_val;

}

add_filter( 'badgeos_user_deserves_credit_deduct', 'badgeos_affiliatewp_user_deserves_credit_deduct_cb', 10, 7 );

/**
 * Check if a valid AffiliateWP trigger found for the given requirements
 *
 * @param $requirements
 * @param $args
 * @return bool
 */
function is_affiliatewp_trigger($requirements, $args) {

    /**
     * AffiliateWP requirements not met yet
     */
    $affiliatewp_triggered = false;

    /**
     * Set our main vars
     */
    $affiliatewp_trigger = $requirements['affiliatewp_trigger'];
    $object_id = $requirements['affiliatewp_object_id'];

    /**
     * Extra arg handling for further expansion
     */
    $object_arg1 = null;

    if ( isset( $requirements['affiliatewp_object_arg1'] ) ) {
        $object_arg1 = $requirements['affiliatewp_object_arg1'];
    }

    /**
     * Triggered object ID (used in these hooks, generally 2nd arg)
     */
    $triggered_object_id = 0;

    $arg_data = $args;

    if ( is_array( $arg_data ) ) {
        if ( isset( $arg_data[ 1 ] ) ) {
            $triggered_object_id = (int) $arg_data[ 1 ];
        }
    }

    /**
     * Use basic trigger logic if no object set
     */
    if( in_array( $affiliatewp_trigger, array_keys($GLOBALS['badgeos_affiliatewp']->triggers) ) ) {
        $affiliatewp_triggered = true;
    }

    return $affiliatewp_triggered;
}


function bos_affwp_get_post_type($post_type) {

    $bos_post_type_settings = array(
        'step' => 'achievement_step_post_type',
        'rank_requirement' => 'ranks_step_post_type',
        'point_award' => 'points_award_post_type',
        'point_deduct' => 'points_deduct_post_type'
    );

    $bos_settings = get_option( 'badgeos_settings', array() );

    $bos_post_type = $bos_settings[ $bos_post_type_settings[$post_type] ];

    if( isset($bos_post_type) && !empty($bos_post_type) ) {
        $post_type = $bos_post_type;
    }

    return $post_type;
}