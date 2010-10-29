var get_owa_information = function() {
if(OWA.util.readCookie){
        var owa_s_val = OWA.util.readCookie("owa_s");
        var owa_s_ident = "sid%3D%3E";
        var owaS_start_index = owa_s_val.indexOf(owa_s_ident);
        //NOTE: This only works as long as sid is the last param in the OWA cookie
                if(owaS_start_index >= 0){
                                var owaSessionID = owa_s_val.substr(owaS_start_index + owa_s_ident.length);
                                                if(document.getElementById("owa_session_id")   &&
						document.getElementById("owa_pageref")  ){					){
                                                                       document.getElementById("owa_session_id").value = owaSessionID;
								       document.getElementById("owa_pageref").value = encode(window.location);
                                                }
                }
}
};

if(jQuery){jQuery(document).ready(get_owa_information);}
