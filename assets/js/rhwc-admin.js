jQuery(document).ready(function($) {

    // Single product generation
    $('#rhwc-generate-btn').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $spinner = $('#rhwc-single-spinner');
        var $msg = $('#rhwc-single-message');
        
        var product_id = $btn.data('product-id');
        var count = $('#rhwc_count').val();
        var rating = $('#rhwc_rating').val();
        var names = $('#rhwc_names').val();
        var style = $('#rhwc_style').val();

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.html('');

        $.ajax({
            url: rhwc_data.ajax_url,
            type: 'POST',
            data: {
                action: 'rhwc_generate_single',
                security: rhwc_data.nonce,
                product_id: product_id,
                count: count,
                rating: rating,
                names: names,
                style: style
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                
                if (response.success) {
                    $msg.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                } else {
                    $msg.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                $msg.html('<div class="notice notice-error inline"><p>An error occurred.</p></div>');
            }
        });
    });

    // Bulk Generation
    $('#rhwc-bulk-generate-btn').on('click', function(e) {
        e.preventDefault();

        if ( ! confirm( rhwc_data.strings.confirm_bulk ) ) {
            return;
        }

        var $btn = $(this);
        var $spinner = $('#rhwc-bulk-spinner');
        var $msg = $('#rhwc-bulk-message');
        var $progressWrapper = $('#rhwc-bulk-progress');
        var $progressBar = $('#rhwc-progress-bar');

        var product_ids = $('#bulk_product_ids').val();
        var count = $('#bulk_count').val();
        var rating = $('#bulk_rating').val();
        var names = $('#bulk_names').val();
        var style = $('#bulk_style').val();
        var exclude_generated = $('#bulk_exclude_generated').is(':checked');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.html('');
        $progressWrapper.show();
        $progressBar.css('width', '50%'); // fake progress

        $.ajax({
            url: rhwc_data.ajax_url,
            type: 'POST',
            data: {
                action: 'rhwc_generate_bulk',
                security: rhwc_data.nonce,
                product_ids: product_ids,
                count: count,
                rating: rating,
                names: names,
                style: style,
                exclude_generated: exclude_generated
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                $progressBar.css('width', '100%');
                
                if (response.success) {
                    $msg.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                } else {
                    $msg.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                $progressBar.css('width', '0%');
                $msg.html('<div class="notice notice-error inline"><p>An error occurred or request timed out.</p></div>');
            }
        });
    });

    // Manage Reviews functions
    var currentPage = 1;

    function loadReviews() {
        var $tbody = $('#rhwc-reviews-tbody');
        var $spinner = $('#rhwc-manage-spinner');
        var filterProduct = $('#filter_product').val();
        var filterRating = $('#filter_rating').val();

        if( ! $('.rhwc-card[data-page="manage-reviews"]').length ) return;

        $spinner.addClass('is-active');

        $.ajax({
            url: rhwc_data.ajax_url,
            type: 'POST',
            data: {
                action: 'rhwc_get_reviews',
                security: rhwc_data.nonce,
                paged: currentPage,
                filter_product: filterProduct,
                filter_rating: filterRating
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                if (response.success) {
                    $tbody.html(response.data.html);
                    $('#rhwc-pagination').html(response.data.pagination);
                }
            }
        });
    }

    // Initial load
    loadReviews();

    $('#rhwc-filter-btn').on('click', function() {
        currentPage = 1;
        loadReviews();
    });

    $(document).on('click', '#rhwc-pagination .page-numbers', function(e) {
        e.preventDefault();
        currentPage = $(this).data('page');
        loadReviews();
    });

    $(document).on('click', '.rhwc-delete-btn', function(e) {
        e.preventDefault();
        if(!confirm(rhwc_data.strings.confirm_delete)) return;
        
        var id = $(this).data('id');
        var $row = $('#review-row-' + id);

        $.post(rhwc_data.ajax_url, {
            action: 'rhwc_delete_review',
            security: rhwc_data.nonce,
            id: id
        }, function(response) {
            if(response.success) {
                $row.fadeOut(function(){ $(this).remove(); });
            }
        });
    });

    $(document).on('click', '.rhwc-toggle-status', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');

        $.post(rhwc_data.ajax_url, {
            action: 'rhwc_toggle_review_status',
            security: rhwc_data.nonce,
            id: id
        }, function(response) {
            if(response.success) {
                var newStatus = response.data.new_status;
                var $badge = $('#review-row-' + id + ' .rhwc-status-badge');
                if(newStatus == '1') {
                    $badge.removeClass('status-pending').addClass('status-approved').text('Approved');
                    $btn.text('Unapprove');
                } else {
                    $badge.removeClass('status-approved').addClass('status-pending').text('Pending');
                    $btn.text('Approve');
                }
            }
        });
    });

    $(document).on('click', '.rhwc-edit-btn', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#review-content-' + id).hide();
        $('#review-edit-' + id).show();
    });

    $(document).on('click', '.cancel-review-edit', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#review-edit-' + id).hide();
        $('#review-content-' + id).show();
    });

    $(document).on('click', '.save-review-edit', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var content = $('#review-edit-' + id + ' textarea').val();
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');

        $.post(rhwc_data.ajax_url, {
            action: 'rhwc_edit_review',
            security: rhwc_data.nonce,
            id: id,
            content: content
        }, function(response) {
            $btn.prop('disabled', false).text('Save');
            if(response.success) {
                $('#review-content-' + id).html(response.data.content).show();
                $('#review-edit-' + id).hide();
            }
        });
    });

});
