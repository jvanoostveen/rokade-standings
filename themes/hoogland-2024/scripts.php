<?php 
	header('Content-type: text/javascript'); 
?>

function iframeLoaded() {
      var iFrameID = document.getElementById('blockrandom');
      if(iFrameID) {
            // here you can make the height, I delete it first, then I make it again
            iFrameID.height = "";
            iFrameID.height = iFrameID.contentWindow.document.body.scrollHeight + "px";
      }   
  }

$(document).ready(function() {
	// READY SET GO
	
	console.log('Hey! Welkom bij Schaken in Hoogland');
})