//<![CDATA[

function validate_form( form )
{
  var msg = [ 'email address', 'first name', 'last name', 'street address', 'city', 'state', 'zip code', 'credit card number', 'the CVV from the back of your card' ];
  
  var fields = ["emailAdd","fname","lname","street","city","state","zip","card_num","cvv"],
      numFields = fields.length,
      i,
      output = '';


  for (i = 0; i < numFields; i++) {
    if (document.getElementById(fields[i]).value == "") 
    {
      output += 'Please include a value for ' + msg[i] + '.\r\n';
    }
    
  }
  
  // validate email address
  var apos=form.emailAdd.value.indexOf("@");
  var dotpos=form.emailAdd.value.lastIndexOf(".");
  
  if (apos<1||dotpos-apos<2)
  {
    output += "Please include a valid email address";
  }
  
  if (output)
  {
    alert(output);
    return false;
  }  
  
  return true;
   
}

//]]>