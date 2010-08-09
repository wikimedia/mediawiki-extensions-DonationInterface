<?php
/*
 * Provides challenges to javascript inline proof of work
 * @param $getResponse is the key to the $challenges array item to be returned
 */
function fnPayflowGetChallenge( $getResponse = NULL ) {
	//get challenges
	$challenges = fnPayflowChallenges();
	
	// if we are just matching key/responses
	if ($getResponse != NULL) {
		return $challenges[$getResponse];
	// else return a random challenge
	} else {
		$randomChallenge = array_rand( $challenges );
		$currProb['key'] = $randomChallenge;
		$currProb += $challenges[$randomChallenge];
		return $currProb;
	}
}

/*
 * Edit this array to provide more challenging challenges
 */
function fnPayflowChallenges() {
	
	$challenges = array(
		'oneplusone' => array('problem' => '1 + 1', 'response' => '2'), 
		'fivetimesthreeplustwo' => array('problem' => '5 * 3 + 2', 'response' => '17'),
		'twoplusone' => array('problem' => '2 + 1', 'response' => '3'),
		'threeplusone' => array('problem' => '3 + 1', 'response' => '4'),
		'fourplusone' => array('problem' => '4 + 1', 'response' => '5'),
		'fiveplusone' => array('problem' => '5 + 1', 'response' => '6'),
		'sixplusone' => array('problem' => '6 + 1', 'response' => '7'),
	);
	
	return $challenges;
}

/*
 * Provides a random number to the javascript inline proof of work to prevent spoofing
 * @param $key is the key to the $matches array item to be returned
 */
function fnPayflowGetRandMatch( $key = NULL ) {
	$matches = fnPayflowRandMatch();
	 
	if ($key != NULL) {
		return $matches[$key];
	} else {
		$randomMatch = array_rand( $matches );
		$currMatch['key'] = $randomMatch;
		$currMatch['match'] = $matches[$randomMatch];

		return $currMatch;
}
	
	
}

/*
 * Edit this array to provide more number matches
 */
function fnPayflowRandMatch() {
	
	$matches = array(
			'0' => '123', 
			'1' => '234',
			'2' => '345',
			'3' => '456',
	);
	
	return $matches;
}

/*
 * Set the dummy key used once the captcha requirement has been satisfied
 */
function fnPayflowGetDummyKey() {
	
	$dummyKey = '0422%1988';	
	
	return $dummyKey;
}



?>