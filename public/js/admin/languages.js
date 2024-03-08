var language = null,
    hasChanges = false;

$( function() {
    $('#language').change(function() {
        languageChanged();
    });

    $('#submit').click(function(e) {
        // e.preventDefault();
        saveLanguage();
    });

    $('.form_element').change(function() {
        hasChanges = true;
    });
});

function languageChanged()
{
    var lang = $('#language').val() || null,
        lang = (lang == 0) ? null : lang;

    if(hasChanges) {
        $('#language').val(language);
        alert('Please save your changes first');
    }

    if(language != null) {
        // saveLanguage(true);
    }

    // console.log(lang, language);
    language = lang;

    if(language) {
        $('.language_hide').show();
        // Dialogs.set('loadingShowing', true);
    } else {
        $('.language_hide').hide();
    }

    // Load language info
    fetchLanguage(language);
}

function fetchLanguage(lang) {
    if(!lang) {
        return;
    }

    $.ajax({
        url: '/admin/languages/fetch/' + lang,
        type: 'GET',
        dataType: 'json',

        success: function(data, statux, xhr) {
            // Dialogs.set('loadingShowing', false);
            console.log('loadSuccess', data);
            console.log('commonWords', data.Language.common_words);
            $('#common_words').html('');
            $('#common_words').html(data.Language.common_words || '');
            hasChanges = false;
        },
        error: function(xhr, status, error) {
            // Dialogs.set('loadingShowing', false);
            alert('An error has occurred');
        }
    });
}

function saveLanguage(fetchLang) {
    
    console.log('saveLanguage');

    $.ajax({
        url: '/admin/languages/save',
        type: 'POST',
        dataType: 'json',
        data: $('#language_form').serialize(),

        success: function(data, statux, xhr) {
            hasChanges = false;
            // Dialogs.set('loadingShowing', false);
            // $('#rendered_space_used').html(data.space_used);
        },
        error: function(xhr, status, error) {
            // Dialogs.set('loadingShowing', false);
            // alert('An error has occurred');
        }
    });
}