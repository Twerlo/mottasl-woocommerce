/**
 * Mottasl WooCommerce Admin Scripts
 */
jQuery(document).ready(function($) {
    'use strict';

    // Media Uploader for Logo
    var mediaUploader;

    $('.mottasl-upload-media-button').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $inputField = $button.siblings('.mottasl-media-url');
        var $previewContainer = $button.parent().siblings('.mottasl-media-preview');

        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Extend the wp.media object
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false // Set to true to allow multiple files to be selected
        });

        // When a file is selected, grab the URL and set it as the text field's value
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $inputField.val(attachment.url);
            if ($previewContainer.length) {
                $previewContainer.html('<img src="' + attachment.url + '" style="max-width:200px; height:auto;" />');
            }
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

    // Basic Tab Navigation
    $('.mottasl-nav-tab-wrapper a.nav-tab').on('click', function(e) {
        e.preventDefault();

        var $tab = $(this);
        var $targetContent = $($tab.attr('href'));

        // Remove active class from all tabs and content
        $('.mottasl-nav-tab-wrapper a.nav-tab').removeClass('nav-tab-active');
        $('.mottasl-tab-content').removeClass('active').hide(); // Hide all content

        // Add active class to the clicked tab and target content
        $tab.addClass('nav-tab-active');
        $targetContent.addClass('active').show(); // Show target content

        // Optional: Update URL hash for deep linking (can be more complex to manage state)
        // window.location.hash = $tab.attr('href');
    });

    // Optional: Activate tab based on URL hash on page load
    // var hash = window.location.hash;
    // if (hash) {
    //     var $link = $('.mottasl-nav-tab-wrapper a.nav-tab[href="' + hash + '"]');
    //     if ($link.length) {
    //         $link.trigger('click');
    //     }
    // } else {
    //     // Ensure the first tab is active if no hash
    //     $('.mottasl-nav-tab-wrapper a.nav-tab:first').trigger('click');
    // }
    // Simplified: if no hash, the default active classes set in HTML will apply.
    // If you want to load a specific tab on page load based on hash:
    if (window.location.hash) {
        var $activeTab = $('.mottasl-nav-tab-wrapper a[href="' + window.location.hash + '"]');
        if ($activeTab.length) {
            $activeTab.click();
        }
    }


    // Initialize WooCommerce Enhanced Selects if not already handled by WC
    // This is often needed if your settings page is not a WC core page
    // Ensure 'wc-enhanced-select' script is enqueued as a dependency for this script in PHP.
    try {
        $(document.body).trigger('wc-enhanced-select-init');
    } catch (e) {
        // Fallback if wc-enhanced-select is not available
        if ($.fn.select2) {
            $('select.wc-enhanced-select').select2();
        }
        console.log('Mottasl: Could not trigger wc-enhanced-select-init. Falling back to standard select2 if available.');
    }

});