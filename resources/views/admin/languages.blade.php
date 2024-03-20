@php
    $javascripts = [
        //'/js/bin/jqGrid-v4.6.0/jquery.js',
        '/js/bin/jqGrid-v4.6.0/js/jquery.jqGrid.js',
        '/js/bin/jqGrid-v4.6.0/js/i18n/grid.locale-en.js',
        '/js/bin/enyo/2.5.1.1/enyo.js',
        '/js/bin/custom/alert/package.js',
        '/js/admin/languages.js'
    ];

    $stylesheets = [
       '/js/bin/jqGrid-v4.6.0/css/ui.jqgrid.css',
       '/css/common.css',
       '/css/admin/config.css',
       '/css/admin/languages.css',
    ];
@endphp

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <script>
            // var bootstrap = @php echo $bootstrap @endphp;
        </script>
        
        <div class='content'>
            <form id='language_form' method="post"> 
                <?php echo csrf_field() ?>
                <div class='container center_div' style='width:400px;background-color: green'> 
                    <div class='config_group' style='_background-color: orange'>
                        <div class='config_block' style='_background-color: blue; width:400px;'>
                            <!-- <h1>General</h1> -->

                            <table style='width:100%'>
                                <tr>
                                    <td class='ralign'>Language: </td>
                                    <td>
                                        <select name="language" id='language'>
                                            <option value='0'>(none)</option>
                                            @foreach($Languages as $L)
                                                <option value='{{$L->code}}'>{{$L->formatNameCode()}}</option>
                                            @endforeach
                                        </select>
                                
                                        <span class='info'>
                                            <span>i</span>
                                            <p>Only languages with Bibles are shown.</p>
                                        </span>
                                    </td>
                                </tr>
                                <tr class='language_hide'>
                                    <td colspan="2">&nbsp;</td>
                                </tr>                                 
                                <tr class='language_hide'>
                                    <th colspan="2">
                                        Common Words: 
                                    </th>
                                </tr>  
                                <tr  class='language_hide'>
                                    <td colspan='2'>
                                        Add words to this list to prevent them from being used as search keywords.
                                        One word per line.
                                    </td>
                                </tr>                                     
                                <tr class='language_hide'>
                                    <td colspan='2'>
                                        <textarea style='width: 98%; height: 100px' name='common_words' id='common_words' class='form_element'></textarea>
                                    </td>
                                </tr>
                                <tr class='language_hide'>
                                    <th colspan="2">
                                        <input type='submit' id='submit' value='Save Language' class='button' />
                                    </th>
                                </tr>    
                            </table>
                        </div>
                    </div>
                </div>
            </form>
            
            <table id='grid'></table>
            <div id='grid_footer'></div>

            <div id='enyo_container'></div>
        </div>
    </div>
@endsection