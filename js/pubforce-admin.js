jQuery(document).ready(function($) {
  $(".kontaktdato").datepicker({
    dateFormat: 'd. M yy',
    showOn: 'button',
    buttonImage: pngurl,
    buttonImageOnly: true,
    numberOfMonths: 1
  });
  
  
  var href = window.location.href;
  if (href.indexOf('edit.php?post_type=butiksside') > -1 ||  href.indexOf('edit.php?post_type=erhvervsside') > -1) {


    // we create a copy of the WP inline edit post function
    var $wp_inline_edit = inlineEditPost.edit;

    // and then we overwrite the function with our own code
    inlineEditPost.edit = function( id ) {

      // "call" the original WP edit function
      // we don't want to leave WordPress hanging
      $wp_inline_edit.apply( this, arguments );

      // now we take care of our business

      // get the post ID
      var $post_id = 0;
      if ( typeof( id ) == 'object' ) {
        $post_id = parseInt( this.getId( id ) );
      }

      if ( $post_id > 0 ) {
        // define the edit row
        var $edit_row = $( '#edit-' + $post_id );
        var $post_row = $( '#post-' + $post_id );

        // get the data
        var $betalt = $( '.column-betalt', $post_row ).text();

        // populate the data
        $( ':input[name="betalt_set"]', $edit_row ).val( $betalt );
      }
    } 
  }  
});