jQuery(document).ready(function($) {
  $(".kontaktdato").datepicker({
    dateFormat: 'd. M yy',
    showOn: 'button',
    buttonImage: pngurl,
    buttonImageOnly: true,
    numberOfMonths: 3
  });
});