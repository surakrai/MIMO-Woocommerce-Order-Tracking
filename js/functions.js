(function($){

  $(document).ready(function(){

    if ( $( '#mimo_date_shipped' ).length ) {
      $( '#mimo_date_shipped' ).datepicker({
        showButtonPanel: true,
      });
    }

    $( '#provider-sortable' ).sortable({
      axis: "y",
      handle: "h3",
      items: '.list_item',
      placeholder: 'ui-state-highlight',
      update: function( event, ui ) {
        $.post( MIMO.ajaxurl, $(this).sortable('serialize') + '&action=update_order_provider', function( data ) {
          //alert(data)
        });
      }
    });


    $( 'body' ).on('click', '#provider-sortable h3', function(e) {

      $(this).next().slideToggle();
      $(this).toggleClass('active');

    });


    $( 'body' ).on('click', '.close-provider', function(e) {

      $(this).closest('.list-item-inner').slideUp(400);

    });
    
    $( 'body' ).on('click', '.update-provider', function(e) {

      e.preventDefault();

      var $item = $(this).closest('.list_item');
      $(this).next().addClass('is-active');

      $.ajax({
        type: 'POST',
        url: MIMO.ajaxurl,
        data: $item.find('input').serialize() + '&action=update_provider',
        success: function(data, textStatus, XMLHttpRequest) {
          //alert(data);
          $item.find('.is-active').removeClass('is-active');
        }

      });

    });

    $('body').on('click', '.add_tracking_url', function(e) {

      if ( $(this).is(":checked") ){
        $(this).prev().val(1)
      }else{
        $(this).prev().val(0)
      }

    });

    $('body').on('click', '.delete-provider', function(e) {

      e.preventDefault();

      var $item = $(this).closest('.list_item');

      $item.find('.spinner').addClass('is-active');

      $.ajax({
        type: 'POST',
        url: MIMO.ajaxurl,
        data: $item.find('input').serialize() + '&action=delete_provider',
        success: function(data, textStatus, XMLHttpRequest) {
          $item.fadeOut(200, function() {
            $(this).remove();
          });
          $item.find('.spinner').removeClass('is-active');
        }

      });

    });

    $('body').on('keypress, keydown, keyup', '.provider-name', function(e) {

      $(this).closest('.list_item').find('h3').text($(this).val())

    });


    $('#add-provider').click(function (e) {

      e.preventDefault();

      $(this).next().addClass('is-active');

      $( '#provider-sortable' ).append(
        '<div id="list_item_9999"  class="list_item">\
          <h3>' + MIMO.provider_name + '</h3>\
          <div class="list-item-inner" style="display:block">\
            <label for="">\
              ' + MIMO.provider_name + '\
              <input type="text" class="widefat provider-name" name="provider_name" id="provider_name" value="">\
            </label>\
            <label for="">\
              '+ MIMO.tracking_url +'\
              <input type="text" class="widefat" name="tracking_url" id="tracking_url" value="">\
            </label>\
            <label for="">\
             <input type="hidden" name="add_tracking_url" value="">\
              <input type="checkbox" class="add_tracking_url" class="checkbox">\
              '+ MIMO.add_tracking_url +'\
            </label>\
            <input type="hidden" class="key" name="key" value="">\
            <div class="control-actions">\
              <div class="alignleft">\
                <a class="widget-control-remove delete-provider" href="#">'+ MIMO.delete +'</a> |\
                <a class="close-provider" href="#">'+ MIMO.close +'</a>\
              </div>\
              <div class="alignright">\
                <input type="submit" class="button button-primary right update-provider" value="'+ MIMO.update +'">\
                <span class="spinner"></span>\
              </div>\
              <br class="clear">\
            </div>\
          </div>\
        </div>'
      );

      $('#provider-sortable').sortable('refresh');

      $.ajax({
        type: 'POST',
        url: MIMO.ajaxurl,
        data: $('#provider-sortable').sortable('serialize') + '&action=add_provider',
        success: function(data, textStatus, XMLHttpRequest) {

          $('.list_item').last().find('.key').val(data);
          $('#list_item_9999').attr( 'id', 'list_item_'+data );
          $('#add-provider').next().removeClass('is-active');

        }

      });

    });


    $( '#mimo-shipment-tracking button' ).on('click', function(e) {

      e.preventDefault();

      var $this = $(this);

      $this.closest('.control-actions').find('.spinner').addClass('is-active');

      $.ajax({
        type: 'POST',
        url: MIMO.ajaxurl,
        data: $('#mimo-shipment-tracking .mimo-field').serialize() +'&mimo_shipment_tracking_nonce=' + $('#mimo_shipment_tracking_nonce').val() + '&action=send_tracking',
        success: function(data, textStatus, XMLHttpRequest) {

          $this.closest('.control-actions').find('.spinner').removeClass('is-active');

          if ( data.errors == true ){
            alert( data.msg );
          }else{
            $('select#order_status').val("wc-completed").trigger('change');
          }

          $( '#mimo-shipment-tracking .control-actions .alignleft' ).html( data.tracking_link );

        },

      });
    });

  });


})(jQuery);