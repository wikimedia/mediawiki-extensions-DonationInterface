<?php

/*
 * Returns the inline javascript necessary to show proof of work
 * @param $challenge is the math problem to solve
 * $key is the array key that will be matched later
 * $randMatch is the key to a random number array that will also be matched
 * to prevent spoofing
 */
function fnPayflowGetInlineJSChallenges( $challenge, $key, $randMatch ) {
	$js = 'function payflowProofofWork() { 
		var solution = ' . $challenge . '; 
		var key = "' . $key . '"; 
		var unique = "' . $randMatch['match'] . '";
		var matchkey = "' . $randMatch['key'] . '";
		var split = "%";
		
		if (document.payment) {
			document.payment.powResponse.value = solution;
			document.payment.powKey.value = key + split + unique;
			document.payment.powRandMatch.value = matchkey;
		
			return true;
		}
	} 
	
	addOnloadHook(payflowProofofWork);';
	
	return $js;
}

