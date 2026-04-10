<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityConnectCustomerToWpUser extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/connect-customer-to-wp-user';
		$this->label       = __( 'Connect customer to WP user', 'latepoint' );
		$this->description = __( 'Links a LatePoint customer record to an existing WordPress user account by their user ID.', 'latepoint' );
		$this->permission  = 'customer__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'customer_id' => [
					'type'        => 'integer',
					'description' => __( 'LatePoint customer ID.', 'latepoint' ),
				],
				'wp_user_id'  => [
					'type'        => 'integer',
					'description' => __( 'WordPress user ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'customer_id', 'wp_user_id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->customer_output_schema();
	}

	public function execute( array $args ) {
		$customer = new OsCustomerModel( (int) $args['customer_id'] );
		if ( $customer->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Customer not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$wp_user_id = (int) $args['wp_user_id'];
		if ( ! get_userdata( $wp_user_id ) ) {
			return new WP_Error( 'wp_user_not_found', __( 'WordPress user not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$customer->wordpress_user_id = $wp_user_id;
		if ( ! $customer->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to link customer to WordPress user.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $customer->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_customer( new OsCustomerModel( $customer->id ) );
	}
}
