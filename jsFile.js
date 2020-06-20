(function ($) {

    "use strict";

    $(document).ready(function () {
        $(document).on('click', '.edit', function () {
            $(this).parent().siblings('td.title').each(function () {
                var content = $(this).html();
                $(this).html('<input value="' + content + '" />');
            });

            $(this).siblings('.save').show();
            $(this).hide();
        });

        $(document).on('click', '.save', function () {

            $('input').each(function () {
                var title = $(this).val();
                $(this).html(title);
                $(this).contents().unwrap();
            });
            var id = $(this).closest('tr').find('.id').text();
            var title = $(this).closest('tr').find('.title').text();
            const apiUrl = customajax.siteurl + '/wp-json/wooapi/v1/changetitle';
            $.ajax({
                url: apiUrl,
                type: 'post',
                data: {
                    access_token: customajax.access_token,
                    ID: id,
                    Title: title
                },
                success: function (result) {
                    alert(result);
                },
                error: function (xhr) {
                    var err = JSON.parse(xhr.responseText);
                    alert(err.message);
                }
            });
            // $.post(apiUrl, {
            //     ID: id,
            //     Title: title
            // }, function (result) {
            //     alert(result);
            // });
            $(this).siblings('.edit').show();
            $(this).hide();

        });

    });


})(jQuery);
