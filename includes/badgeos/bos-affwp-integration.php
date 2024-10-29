<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class BadgeOS_AffiliateWP_Integration
 */
class BadgeOS_AffiliateWP_Integration {

    /**
	 * BadgeOS AffiliateWP Triggers
	 *
	 * @var array
	 */
	public $triggers = array();

	/**
	 * Actions to forward for splitting an action up
	 *
	 * @var array
	 */
	public $actions = array();


    /**
     * BadgeOS_AffiliateWP_Integration constructor.
     */
    public function __construct() {

        /**
         * AffiliateWP Action Hooks
         */
		$this->triggers = array(
            'affwp_insert_affiliate' => __( 'Affiliate Sign-up', 'bos-awp' ),
            'affwp_insert_referral' => __( 'Earn a Referral', 'bos-awp' ),
            'badgeos_affwp_set_referral_status_paid' => __( 'Referral Paid', 'bos-awp' ),
            'badgeos_affwp_set_referral_status_rejected' => __( 'Referral Rejected', 'bos-awp' ),
            'affwp_post_insert_visit' => __( 'Referral Visit', 'bos-awp' ),
            'user_register' => __( 'New User Sign-up through an Affiliate', 'bos-awp' ),
		);

		/**
         * Actions that we need split up
         */
		$this->actions = array(
			'affwp_insert_affiliate' =>  'badgeos_affwp_insert_affiliate',
			'affwp_insert_referral' =>  'badgeos_affwp_insert_referral',
			'affwp_post_insert_visit' =>  'badgeos_affwp_post_insert_visit',
			'affwp_set_referral_status' =>  array(
			    'actions' => array(
			        'badgeos_affwp_set_referral_status_paid',
                    'badgeos_affwp_set_referral_status_rejected'
                )
            ),
        );
        
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 11 );

    }

    /**
     * include files if plugin meets requirements
     */
	public function plugins_loaded() {

		if ( $this->meets_requirements() ) {

            if( file_exists( BOS_AWP_INCLUDES_DIR . 'badgeos/rules-engine.php' ) ) {
                require_once ( BOS_AWP_INCLUDES_DIR . 'badgeos/rules-engine.php' );
            }

            if( file_exists( BOS_AWP_INCLUDES_DIR . 'badgeos/steps-ui.php' ) ) {
                require_once ( BOS_AWP_INCLUDES_DIR . 'badgeos/steps-ui.php' );
            }

			$this->action_forwarding();
		}
    }
    
    /**
     * Check if BadgeOS is available
     *
     * @return bool
     */
	public static function meets_requirements() {

		if ( !class_exists( 'BadgeOS' ) || !function_exists( 'badgeos_get_user_earned_achievement_types' ) ) {

			return false;
		} elseif ( !class_exists( 'Affiliate_WP' ) ) {

			return false;
		}

		return true;
	}

    /**
     * Forward WP actions into a new set of actions
     */
	public function action_forwarding() {
		foreach ( $this->actions as $action => $args ) {
			$priority = 10;
			$accepted_args = 20;

			if ( is_array( $args ) ) {
				if ( isset( $args[ 'priority' ] ) ) {
					$priority = $args[ 'priority' ];
				}

				if ( isset( $args[ 'accepted_args' ] ) ) {
					$accepted_args = $args[ 'accepted_args' ];
				}
			}

			add_action( $action, array( $this, 'action_forward' ), $priority, $accepted_args );
		}
	}

    /**
     * Forward a specific WP action into a new set of actions
     *
     * @return mixed|null
     */
	public function action_forward() {
		$action = current_filter();
		$args = func_get_args();
		$action_args = array();

		if ( isset( $this->actions[ $action ] ) ) {
			if ( is_array( $this->actions[ $action ] )
				 && isset( $this->actions[ $action ][ 'actions' ] ) && is_array( $this->actions[ $action ][ 'actions' ] )
				 && !empty( $this->actions[ $action ][ 'actions' ] ) ) {
				foreach ( $this->actions[ $action ][ 'actions' ] as $new_action ) {
			
					$action_args = $args;

					array_unshift( $action_args, $new_action );

					call_user_func_array( 'do_action', $action_args );
				}

				return null;
			} elseif ( is_string( $this->actions[ $action ] ) ) {
				$action =  $this->actions[ $action ];
			}
		}
		array_unshift( $args, $action );

		return call_user_func_array( 'do_action', $args );
	}
}

/**
 * Initiate plugin main class
 */
$GLOBALS['badgeos_affiliatewp'] = new BadgeOS_AffiliateWP_Integration();