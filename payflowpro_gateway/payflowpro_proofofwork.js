function payflowproPOW(){
	
	var ajaxRequest;  // The variable that makes Ajax possible!
	
	try{
		// Opera 8.0+, Firefox, Safari
		ajaxRequest = new XMLHttpRequest();
	} catch (e){
		// Internet Explorer Browsers
		try{
			ajaxRequest = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try{
				ajaxRequest = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e){
				// Something went wrong
				alert("Your browser is not compatible.  TODO: Add Instruction");
				return false;
			}
		}
	}
	
	url = document.location;
	param = "&requireCap=require&powResponse=mister";
	ajaxRequest.open("POST", url, true); 
	
	//Send the proper header information along with the request
	ajaxRequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	ajaxRequest.setRequestHeader("Content-length", param.length);
	ajaxRequest.setRequestHeader("Connection", "close");
	
	// Create a function that will receive data sent from the server
	ajaxRequest.onreadystatechange = function(){
		if(ajaxRequest.readyState == 4) {
				document.getElementsByTagName("body")[0].innerHTML = ajaxRequest.responseText;
				document.payment.powResponse.value = "mister";				
		}
	}

	ajaxRequest.send(param);
	
	

	
}

payflowproPOW();



