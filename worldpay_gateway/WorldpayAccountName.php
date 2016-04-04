<?php

class WorldpayAccountName extends DonorFullName {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		parent::stage( $adapter, $unstagedData, $stagedData );

		// FIXME: how about we just use the generic field name.
		$stagedData['wp_acctname'] = $stagedData['full_name'];
	}
}
