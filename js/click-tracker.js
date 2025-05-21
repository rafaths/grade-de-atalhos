jQuery(document).ready(function($) {
    $('.ga-atalho').on('click', function(e) {
        var postId = $(this).data('id');
        $.post(ga_vars.ajax_url, {
            action: 'ga_contador',
            post_id: postId
        });
    });
});
