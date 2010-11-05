// OWA Tracker Min file created 1286236498 

//// Start of json2 //// 

//// End of owa.tracker //// 

///hard-coded///
//<![CDATA[
//OWA.setSetting('debug', true);
// Set base URL
OWA.setSetting('baseUrl', 'http://owa.tesla.usability.wikimedia.org/owa/');
//OWA.setApiEndpoint('http://analytics.tesla.usability.wikimedia.org/wiki/d/index.php?action=owa&owa_specialAction');
// Create a tracker
OWATracker = new OWA.tracker();
OWATracker.setEndpoint('http://owa.tesla.usability.wikimedia.org/owa/');
OWATracker.setSiteId('75af9f1681f6a30265361e3a951fa331');
OWATracker.trackPageView();
OWATracker.trackClicks();
OWATracker.trackDomStream();
//]]>
