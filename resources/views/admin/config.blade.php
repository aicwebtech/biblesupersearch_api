<?php
    $mail_drivers = ["smtp", "mail", "sendmail", "mailgun", "mandrill", "ses", "log"];

    $javascripts = [
        '/js/bin/enyo/2.5.1.1/enyo.js',
        '/js/bin/custom/alert/package.js',
        '/js/admin/config.js',
    ];

    $stylesheets = [
       '/css/admin/config.css',
    ];
?>

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <script> 
            var downloadCacheSize = {{$configs['download.cache.cache_size']}};
            var downloadTempCacheSize = {{$configs['download.cache.temp_cache_size']}};
        </script>

        <div class='content' style='margin-left: 200px; margin-right: 200px;'>
            <form method='POST'>
                <?php echo csrf_field() ?>

                <div id='tabs'>
                    <ul>
                        <li><a href='#tab_basic'>General Settings</a></li>
                        <li><a href='#tab_download'>Bible Downloads</a></li>
                        <li><a href='#tab_advanced'>Advanced</a></li>
                    </ul>

                    <div id='tab_basic' class='config_tab'>
                        <div class='container' style='width:750px'> 
                            <div class='config_group'>
                                <div class='config_block'>
                                    <h1>General</h1>

                                    <table>
                                        <tr>
                                            <td class='ralign'>Application Name: </td>
                                            <td><input name='app__name' size='50' value='{{$configs['app.name']}}'></td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Default Highlight Tag: </td>
                                            <td>
                                                <select name='bss__defaults__highlight_tag' style='width: 100px'>
                                                    @foreach($hl_tags as $tag)
                                                    <option value='{{$tag}}'
                                                        @if($configs['bss.defaults.highlight_tag'] == $tag)selected='selected'@endif>&lt;{{$tag}}&gt;</option>
                                                    @endforeach
                                                </select>
                                                <span class='info'>
                                                    <span>i</span>
                                                    <p>
                                                        HTML tag used for highlighting keywords in search results.
                                                    </p>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Application URL: </td>
                                            <td>
                                                <a name='application_url' />
                                                <input name='app__url' size='50' value='{{$configs['app.url']}}'>
                                                <span class='info'>
                                                    <span>i</span>
                                                    <p>Base URL of this Bible SuperSearch API install.</p>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Client URL: </td>
                                            <td>
                                                <input name='app__client_url' size='50' value='{{$configs['app.client_url']}}'>
                                                <span class='info'>
                                                    <span>i</span>
                                                    <p>
                                                        URL to a Bible SuperSearch client or webpage using your API. &nbsp; When provided, will be used to make
                                                        'See API in action' link in documentation.
                                                    </p>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Use Config Caching: </td>
                                            <td>
                                                <label for='config_cache_1'>Yes</label>
                                                <input type='radio' name='app__config_cache' value='1' id='config_cache_1' @if($configs['app.config_cache'] == 1)checked='checked'@endif />
                                                <label for='config_cache_2'>No</label>
                                                <input type='radio' name='app__config_cache' value='0' id='config_cache_2' @if($configs['app.config_cache'] == 0)checked='checked'@endif />
                                                <span class='info'>
                                                    <span>i</span>
                                                    <p>
                                                        Enabling config cache may improve performance.  Note: Enabling this will cause the text configs in .env to be cached and
                                                        any changes made in .env will be ignored until this is disabled.
                                                    </p>
                                                </span>
                                            </td>
                                        </tr>                                        
                                        <tr>
                                            <td class='ralign'>Allow Phoning Home: </td>
                                            <td>
                                                <a id='phone_home' />
                                                <label for='phone_home_1'>Yes</label>
                                                <input type='radio' name='app__phone_home' value='1' id='phone_home_1' @if($configs['app.phone_home'] == 1)checked='checked'@endif />
                                                <label for='phone_home_2'>No</label>
                                                <input type='radio' name='app__phone_home' value='0' id='phone_home_2' @if($configs['app.phone_home'] == 0)checked='checked'@endif />
                                                <span class='info'>
                                                    <span>i</span>
                                                    <p>
                                                        Allows this API to contact the official Bible SuperSearch API on api.biblesupersearch.com
                                                        This access is used solely for checking for updates.
                                                        Keep this disabled for more privacy and anonymity.  
                                                    </p>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                @if(config('app.premium'))
                                    <div class='config_block'>
                                        <h1>Premium</h1>

                                        <table>
                                            <tr>
                                                <td class='ralign'>License Status: </td>
                                                <td><!-- INSERT STATUS HERE -->{{$configs['lc.confirm']}}</td>
                                            </tr>                                            
                                            <tr>
                                                <td class='ralign'>License Key: </td>
                                                <td><input name='lc__key' size='50' value='{{$configs['lc.key']}}'></td>
                                            </tr>
                                            <tr>
                                                <td class='ralign'>License Expires: </td>
                                                <td><!-- INSERT LIC EXPIRE / RENEWAL DATE HERE --></td>
                                            </tr>
                                        </table>
                                    </div>
                                @endif

                                <div class='config_block'>
                                    <h1>Bible List</h1>
                                    <table>
                                        <tr>
                                            <td class='ralign'>Default Bible: </td>
                                            <td>
                                                <select name='bss__defaults__bible' style='width: 300px'>
                                                    @foreach($bibles as $bible)
                                                    <option value='{{$bible->module}}'
                                                        @if($configs['bss.defaults.bible'] == $bible->module)selected='selected'@endif>{{$bible->name}}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Default Sort Order: </td>
                                            <td>
                                                <select name='bss__defaults__bible_sord' style='width: 300px' disabled="disabled">
                                                    <option>Rank</option>
                                                    {{-- @foreach($bibles as $bible)
                                                    <option value='{{$bible->module}}'
                                                        @if($configs['bss.defaults.bible_sord'] == $bible->module)selected='selected'@endif>{{$bible->name}}</option>
                                                    @endforeach --}}
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class='config_block'>
                                    <h1>Limitations</h1>

                                    <table>
                                        <tr>
                                            <td class='ralign'>Verses Per Page: </td>
                                            <td>
                                                <input name='bss__pagination__limit' size='5' value='{{$configs['bss.pagination.limit']}}'>
                                                <span class='info'>
                                                    <span>i</span>
                                                    <p>The maximum number of verses displayed per page for paginated search results.</p>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Overall Maximum Verses: </td>
                                            <td>
                                                <input name='bss__global_maximum_results' size='5' value='{{$configs['bss.global_maximum_results']}}'>
                                                <span class='info'>
                                                    <span>i</span>
                                                    <p>
                                                        Total maximum number of verses returned by ANY query. &nbsp;Users are advised to narrow their searches if this value is
                                                        exceeded. &nbsp;This helps prevent overload of your server. &nbsp; Also, most Bible publishers do not allow displaying
                                                        more than 500 verses at once.
                                                    </p>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Daily Access Limit: </td>
                                            <td><input name='bss__daily_access_limit' size='5' value='{{$configs['bss.daily_access_limit']}}'> hits</td>
                                        </tr>
                                    </table>
                                </div>
                                <div style="clear:both"></div>
                            </div>
                        </div>
                    </div>

                    <div id='tab_download' class='config_tab'>
                        <div class='container' style='width:800px'> 
                            <div class='config_group'>
                                <div class='config_block'>
                                    <h1>Bible Downloads &amp; Exports</h1>

                                    <table border='0'>
                                        <tbody>
                                            <tr>
                                                <th colspan='2'>
                                                    Please help us share God's Word around the world by enabling this feature!
                                                </th>
                                            </tr>
                                            <tr>
                                                <td class='ralign' style='width:320px'>Enable Bible Downloads: </td>
                                                <td>
                                                    <label for='download_enable_1'>Yes</label>
                                                    <input
                                                        type='radio' name='download__enable' value='1' id='download_enable_1'
                                                        @if($configs['download.enable'] == 1)checked='checked'@endif
                                                        @if(!$render_writeable)disabled='disabled'@endif
                                                     />
                                                    <label for='download_enable_0'>No</label>
                                                    <input
                                                        type='radio' name='download__enable' value='0' id='download_enable_0'
                                                        @if($configs['download.enable'] == 0)checked='checked'@endif
                                                        @if(!$render_writeable)disabled='disabled'@endif
                                                        />
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            This enables the basic Bible download functionality, including downloading of Bibles via the API.
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>
                                            @if(!$render_writeable)

                                            <tr>
                                                <td colspan="2" class='error'>
                                                    Notice: Cannot write to render directory:  <br />

                                                    <pre>{{$render_dir}}</pre>

                                                    Please make it writable to the web process before you enable the download feature.
                                                </td>
                                            </tr>

                                            @else

                                            <tr>
                                                <td>&nbsp;</td>
                                                <td style='text-align: right'>
                                                    <small>Space used: <span id='rendered_space_used'>{{$rendered_space}}</span>MB</small>
                                                    <button id='button_clean_up_rendered' class='button-small ui-button ui-corner-all ui-widget'>Clean Up</button>
                                                    <button id='button_clear_all_rendered' class='button-small ui-button ui-corner-all ui-widget'>Delete All</button>
                                                </td>
                                            </tr>
                                            
                                            @endif
                                        </tbody>
                                        <tbody class='download_addl_settings' @if($configs['download.enable'] == 0)style='display:none'@endif>
                                            <tr><td colspan='3'>&nbsp;</td></tr>
                                            <tr>
                                                <td class='ralign'>Enable Downloads Tab: </td>
                                                <td>
                                                    <label for='download_tab_enable_1'>Yes</label>
                                                    <input
                                                        type='radio' name='download__tab_enable' value='1' id='download_tab_enable_1'
                                                        @if($configs['download.tab_enable'] == 1)checked='checked'@endif
                                                     />
                                                    <label for='download_tab_enable_0'>No</label>
                                                    <input
                                                        type='radio' name='download__tab_enable' value='0' id='download_tab_enable_0'
                                                        @if($configs['download.tab_enable'] == 0)checked='checked'@endif
                                                        />
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            This enables a tab with links to download files on the API documentation page.
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2">&nbsp;</td>
                                            </tr>       
                                            <tr>
                                                <td class='ralign'>Maximum Bibles Downloadable: </td>
                                                <td>
                                                    <input name='download__bible_limit' size='5' value='{{$configs['download.bible_limit']}}'>
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            Maximum number of Bibles that can be downloaded at once.  0 = no limit (requires good web hosting).
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>         
                                            <tr>
                                                <td colspan="2">&nbsp;</td>
                                            </tr>                            
                                            <tr><th colspan="2">Rendered / Retained Files Settings</th></tr>
                                            <tr>
                                                <td colspan="2">&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td class='ralign'>Temporary Space for Rendered Files: </td>
                                                <td>
                                                    <input 
                                                        name='download__cache__temp_cache_size' id='download_temp_cache_size' size='5' class='download_size'
                                                        value='{{$configs['download.cache.temp_cache_size']}}'> MB
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            This space is used temporarily to hold rendered Bible files.  Files will be cleaned up after download.
                                                            Warning:  Setting this two low will limit the number of Bibles that can be downloaded at once. 
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>                             
                                            <tr>
                                                <td colspan="2">
                                                    <p>
                                                        When a download is requested by a user, the Bible(s) will be renderd into the selected format on the fly.
                                                        Some of these formats may take longer than others to render.  You have the option to retain rendered files
                                                        for quicker download.
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class='ralign' style='width:320px'>Retain Rendered Files: </td>
                                                <td>
                                                    <label for='download_retain_1'>Yes</label>
                                                    <input
                                                        type='radio' name='download__retain' value='1' id='download_retain_1'
                                                        @if($configs['download.retain'] == 1)checked='checked'@endif
                                                     />
                                                    <label for='download_retain_0'>No</label>
                                                    <input
                                                        type='radio' name='download__retain' value='0' id='download_retain_0'
                                                        @if($configs['download.retain'] == 0)checked='checked'@endif
                                                        />
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            This enables saving of rendered Bibles for quicker downloads later.
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tbody id='retained_file_settings' @if($configs['download.retain'] == 0 || $configs['download.enable'] == 0)style='display:none'@endif >
                                            <tr>
                                                <td colspan="2">&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2">
                                                    <div id='rendered_space_slider'></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2">&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td class='ralign'>Space for Retained Files: </td>
                                                <td>
                                                    <input 
                                                        name='download__cache__cache_size' id='download_cache_size' size='5' class='download_size' 
                                                        value='{{$configs['download.cache.cache_size']}}'> MB
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            Maximum allowable disk space for retained files.  Set to 0 to disable file retention (not reccommended).
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>                                            
                                            <tr>
                                                <td class='ralign'>Total Space Reserved: </td>
                                                <td>
                                                    <input 
                                                        name='download__cache__total_cache_size' id='download_total_cache_size' size='5' class='download_size'
                                                        value='{{$configs['download.cache.cache_size'] + $configs['download.cache.temp_cache_size']}}'
                                                        > MB
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            Maximum allowable disk space for any temporary or retained Bible files.
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2">&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td class='ralign'>Days to Retain Files: </td>
                                                <td>
                                                    <input name='download__cache__days' size='5' value='{{$configs['download.cache.days']}}'>
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>Number of days to retain a Bible file since it's last download before being deleted.  0 = unlimited days.  </p>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class='ralign'>Maximum File Size: </td>
                                                <td>
                                                    <input name='download__cache__max_filesize' size='5' value='{{$configs['download.cache.max_filesize']}}'> MB
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            Maximum allowable size of retained files.  Files larger than this will be deleted immediately after download.
                                                            Most Bibles will be about 5 - 10 MB.  0 = unlimited size
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>                                
                                            <tr>
                                                <td class='ralign'>Minimum Rendering Time: </td>
                                                <td>
                                                    <input name='download__cache__min_render_time' size='5' value='{{$configs['download.cache.min_render_time']}}'> Seconds
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            Files that take LESS time than this to render will never be retained. 0 = no restriction
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>                                
                                            <tr>
                                                <td class='ralign'>Minimum Hits: </td>
                                                <td>
                                                    <input name='download__cache__min_hits' size='5' value='{{$configs['download.cache.min_hits']}}'> 
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            Rendered files that have been requested less than this amount will never been retained. 0 = no restriction
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tbody class='download_addl_settings' @if($configs['download.enable'] == 0)style='display:none'@endif>
                                            <!-- <tr><td colspan="2">&nbsp;</td></tr> -->
                                            <tr><td colspan="2">&nbsp;</td></tr>
                                            <tr><th colspan="2">Copyright Settings</th></tr>
                                            <tr><td colspan="2">&nbsp;</td></tr>
                                            <tr><td colspan="2">All Bible files include a copyright statement, even if the text is in the public domain.</td></tr>
                                            <tr><td colspan="2">&nbsp;</td></tr>
                                            <tr><th colspan="2">Derivative Copyright Notice</th></tr>
                                            <tr><td colspan="2">&nbsp;</td></tr>
                                            <tr><td colspan="2">If provided, will be appended to the copyright notice on each Bible file.</td></tr>
                                            <tr>
                                                <td colspan="2">
                                                    HTML is allowed, but may be stripped out depending on the format selected.  YYYY will be replaced by the current year.
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2">
                                                    <textarea style='width: 100%; height: 100px'
                                                        name='download__derivative_copyright_statement'>{{$configs['download.derivative_copyright_statement']}}</textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class='ralign' style='width:269px'>Add link to <a href='#application_url' title='Application URL'>{{config('app.url')}}</a>: </td>
                                                <td>
                                                    <label for='app_link_enable_1'>Yes</label>
                                                    <input
                                                        type='radio' name='download__app_link_enable' value='1' id='app_link_enable_1'
                                                        @if($configs['download.app_link_enable'] == 1)checked='checked'@endif
                                                     />
                                                    <label for='app_link_enable_0'>No</label>
                                                    <input
                                                        type='radio' name='download__app_link_enable' value='0' id='app_link_enable_0'
                                                        @if($configs['download.app_link_enable'] == 0)checked='checked'@endif
                                                        />
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            Adds a link to {{config('app.url')}} (Application URL) to the copyright information.
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>                                
                                            <tr>
                                                <td class='ralign' style='width:269px'>Add link to BibleSuperSearch.com: </td>
                                                <td>
                                                    <label for='bss_link_enable_1'>Yes</label>
                                                    <input
                                                        type='radio' name='download__bss_link_enable' value='1' id='bss_link_enable_1'
                                                        @if($configs['download.bss_link_enable'] == 1)checked='checked'@endif
                                                     />
                                                    <label for='bss_link_enable_0'>No</label>
                                                    <input
                                                        type='radio' name='download__bss_link_enable' value='0' id='bss_link_enable_0'
                                                        @if($configs['download.bss_link_enable'] == 0)checked='checked'@endif
                                                        />
                                                    <span class='info'>
                                                        <span>i</span>
                                                        <p>
                                                            Adds a link to BibleSuperSearch.com to the copyright information.
                                                        </p>
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id='tab_advanced' class='config_tab'>
                        <div class='container' style='width:750px'> 
                            <div class='config_group'>
                                <div class='config_block'>
                                    <h1>System Mail</h1>

                                    <table>
                                        <tr>
                                            <td class='ralign'>System Mail From Name: </td>
                                            <td><input name='mail__from__name' size='50' value='{{$configs['mail.from.name']}}'></td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>System Mail Address: </td>
                                            <td><input name='mail__from__address' size='50' value='{{$configs['mail.from.address']}}'></td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Mail Driver: </td>
                                            <td>
                                                <select name='bss__driver' style='width: 300px'>
                                                    @foreach($mail_drivers as $dr)
                                                    <option value='{{$dr}}'
                                                        @if($configs['mail.driver'] == $dr)selected='selected'@endif>{{$dr}}</option>
                                                    @endforeach
                                                </select>
                                                <span class='info narrow'>
                                                    <span>i</span>
                                                    <p>Default: sendmail</p>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Sendmail Path: </td>
                                            <td>
                                                <input name='mail__sendmail' size='38' value='{{$configs['mail.sendmail']}}' readonly="readonly">
                                                <span class='info narrow'>
                                                    <span>i</span>
                                                    <p>Default: /usr/sbin/sendmail -bs</p>
                                                </span>
                                                <button class='enable button button-small'>Enable</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Mail Username: </td>
                                            <td><input name='mail__username' size='50' value='{{$configs['mail.username']}}'></td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Mail Password: </td>
                                            <td><input name='mail__password' size='50' value='{{$configs['mail.password']}}'></td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Mail Host: </td>
                                            <td><input name='mail__host' size='50' value='{{$configs['mail.host']}}'></td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Mail Port: </td>
                                            <td>
                                                <input name='mail__port' size='38' value='{{$configs['mail.port']}}' readonly="readonly">
                                                <span class='info narrow'>
                                                    <span>i</span>
                                                    <p>Default: 587</p>
                                                </span>
                                                <button class='enable button button-small'>Enable</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class='ralign'>Mail Encryption: </td>
                                            <td>
                                                <input name='mail__encryption' size='38' value='{{$configs['mail.encryption']}}'  readonly="readonly">
                                                <span class='info narrow'>
                                                    <span>i</span>
                                                    <p>Default: tls</p>
                                                </span>
                                                <button class='enable button button-small'>Enable</button>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div style="clear:both"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <br />
                <div style='text-align: center'>
                    <input type='submit' value='Save Configs' class='button' />
                </div>
            </form>
            <div id='dialog_container'></div>
        </div>
    </div>
@endsection

