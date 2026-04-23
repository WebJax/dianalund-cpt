		jQuery(document).ready(function($) {
			// Set all variables to be used in scope
			var frame,
					addImgLink = $('#hent-logo'),
					delImgLink = $('#fjern-logo'),
					imgContainer = $('#allround-cpt-logo'),
					imgIdInput = $('#allround-cpt_logo_id' );
		

			// ADD IMAGE LINK
			addImgLink.on( 'click', function( event ){

				event.preventDefault();

				// If the media frame already exists, reopen it.
				if ( frame ) {
					frame.open();
					return;
				}

				// Create a new media frame
				frame = wp.media.frames.file_frame = wp.media({
					title: 'Find logoet',
					button: {
						text: 'Brug dette logo'
					},
					multiple: false  // Set to true to allow multiple files to be selected
				});


				// When an image is selected in the media frame...
				frame.on( 'select', function() {

					// Get media attachment details from the frame state
					var attachment = frame.state().get('selection').first().toJSON();

					// Send the attachment URL to our custom image input field.
					imgContainer.attr( 'src', attachment.url );
					imgContainer.removeClass ('hidden');

					// Send the attachment id to our hidden input
					imgIdInput.val( attachment.id );

					// Hide the add image link
					addImgLink.addClass( 'hidden' );

					// Unhide the remove image link
					delImgLink.removeClass( 'hidden' );
				});

				// Finally, open the modal on click
				frame.open();
			});


			// DELETE IMAGE LINK
			delImgLink.on( 'click', function( event ){

				event.preventDefault();

				// Clear out the preview image
				imgContainer.addClass ( 'hidden' );
				imgContainer.html( '' );

				// Un-hide the add image link
				addImgLink.removeClass( 'hidden' );

				// Hide the delete image link
				delImgLink.addClass( 'hidden' );

				// Delete the image id from the hidden input
				imgIdInput.val( '' );

			});
	});