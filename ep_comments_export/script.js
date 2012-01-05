var epce = {
	init : function () {
		epce.dl();
	},
	dl : function() {
		jQuery('.epce_comment_export a').click(function(){
			jQuery(this).addClass('loading');
		});
		
		
		// Check if the input has any value
		if(jQuery('.epce_comment_export input').val()) {
			// Save value
			var csvfile = jQuery('.epce_comment_export input').val();
			
			// Check if the input has a success class, if so everything is good.
			if(jQuery('.epce_comment_export input').hasClass('success')) {
				// Save value
				var csvfile = jQuery('.epce_comment_export input').val();
				// Redirect to the CSV file
				document.location.href = csvfile;
				jQuery('.epce_comment_export a').removeClass('loading');
			}
			// Else, display the error
			else {
				alert(csvfile);
			}
		}
	}
}

jQuery(document).ready(function() {
	epce.init();
});