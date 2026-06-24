jQuery(document).ready(function ($) {

  //Header
  var header = $('#masthead');
  var lastScroll = 0;
  var trigger = 120;
  $(window).on('scroll', function(){
    var currentScroll = $(this).scrollTop();
    // Add fixed header
    if(currentScroll > trigger){
      header.addClass('sticky-header');
    } else {
      header.removeClass('sticky-header show-header');
    }
    // Show/hide based on scroll direction
    if(currentScroll < lastScroll){
      header.addClass('show-header');
    } else {
      header.removeClass('show-header');
    }
    lastScroll = currentScroll;
  });

  //Checkout
  $(window).on('load', function(){
    if($('#createaccount').length > 0) {
      $('#createaccount').trigger('click');
    }
  });
});
