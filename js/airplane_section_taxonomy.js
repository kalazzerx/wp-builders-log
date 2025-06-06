jQuery(document).ready(function($) {
    var taxonomy = airplane_section_taxonomy.slug || 'airplane_section';
    // Get ajaxurl from WordPress global or from localized script
    var ajaxurl = window.ajaxurl || airplane_section_taxonomy.ajaxurl;
    
    if (!ajaxurl) {
        console.error('AJAX URL not properly defined');
        return;
    }
    
    // Handle tab switching for the airplane section meta box
    $('#' + taxonomy + '-tabs a').on('click', function(e) {
        e.preventDefault();
        var t = $(this).attr('href');
        
        // Update tab UI - add 'tabs' class to active tab and remove from siblings
        $(this).parent().addClass('tabs').siblings('li').removeClass('tabs');
        
        // Hide all panels with class tabs-panel inside this taxonomy box
        $('#taxonomy-' + taxonomy + ' .tabs-panel').hide();
        
        // Show the selected panel (t contains the ID with # prefix)
        console.log('Showing panel:', t);
        $(t).show();
        
        // If this is the "most used" tab, ensure the radio selections stay in sync
        if(t === '#' + taxonomy + '-pop') {
            // Find which radio is checked in the main panel
            var checked = $('#' + taxonomy + 'checklist li :radio:checked');
            if(checked.length) {
                var id = checked.val();
                // Check the corresponding radio in the popular list
                $('#in-popular-' + taxonomy + '-' + id).prop('checked', true);
            }
        } else {
            // If switching to all terms tab, sync from most-used to all if needed
            var checked = $('#' + taxonomy + 'checklist-pop li :radio:checked');
            if(checked.length) {
                var id = checked.val();
                // Check the corresponding radio in the all list
                $('#in-' + taxonomy + '-' + id).prop('checked', true);
            }
        }
        
        return false;
    });

    // Handle radio button clicks
    $(document).on('click', '#' + taxonomy + 'checklist li :radio, #' + taxonomy + 'checklist-pop :radio', function(){
        var t = $(this), c = t.is(':checked'), id = t.val();
        $('#' + taxonomy + 'checklist li :radio, #' + taxonomy + 'checklist-pop :radio').prop('checked',false);
        $('#in-' + taxonomy + '-' + id + ', #in-popular-' + taxonomy + '-' + id).prop( 'checked', c );
    });

    // Handle adding a new term
    $(document).on('click', '#' + taxonomy +'-add .radio-tax-add', function(){
        var term = $('#' + taxonomy+'-add #new'+taxonomy).val();
        if (!term || term === '' || term === $('#' + taxonomy+'-add #new'+taxonomy).attr('placeholder')) {
            alert('Please enter a name for the new section');
            return;
        }
        
        var nonce = $('#' + taxonomy+'-add #_wpnonce_radio-add-tag').val();
        
        console.log('Adding new term:', term);
        console.log('Using ajaxurl:', ajaxurl);
        
        $.post(ajaxurl, {
            action: 'radio_tax_add_taxterm',
            term: term,
            '_wpnonce_radio-add-tag': nonce,
            taxonomy: taxonomy
        }, function(r){
            console.log('AJAX Response:', r); // Debug log to see what's coming back
            
            // Reset value to placeholder after attempting to add
            $('#' + taxonomy+'-add #new'+taxonomy).val('');
            
            if (r && r.success && r.data) {
                // Append the new HTML to the checklist
                $('#' + taxonomy + 'checklist').append(r.data.html);
                
                // Force a pause to ensure DOM is updated before trying to select the element
                setTimeout(function() {
                    // First, ensure all radio buttons are unchecked
                    $('#' + taxonomy + 'checklist li :radio').prop('checked', false);
                    
                    // Then find the newly added radio button - it should be the last one in the list
                    var newRadio = $('#' + taxonomy + 'checklist li:last-child :radio');
                    newRadio.prop('checked', true);
                    
                    // Make the UI change obvious - add a brief highlight effect
                    var newItem = newRadio.closest('li');
                    newItem.css('background-color', '#ffff99')
                           .delay(800)
                           .queue(function(next){
                               $(this).css('background-color', '');
                               next();
                           });
                }, 50);
                
                // Show success message
                console.log('Successfully added new term:', r.data);
            } else {
                // If response indicates an error, show details
                console.error('Failed to add new term:', r);
                var errorMsg = r && r.data && r.data.message ? r.data.message : 'Unknown error occurred';
                alert('Failed to add new term: ' + errorMsg + '\nPlease try again or refresh the page.');
            }
        }).fail(function(xhr, status, error) {
            // Handle complete AJAX failure
            console.error('AJAX request failed:', status, error);
            alert('Failed to communicate with the server. Please refresh the page and try again.');
        });
    });
});
