<?php
    $mail_drivers = ["smtp", "mail", "sendmail", "mailgun", "mandrill", "ses", "log"];

    $javascripts = [
        '/js/bin/enyo/2.5.1.1/enyo.js',
        '/js/bin/custom/alert/package.js'
    ]
?>

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <div class='content' style='margin-left: 200px; margin-right: 200px;'>
            <form method='POST'>
                <?php echo csrf_field() ?>
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
                        </table>
                    </div>
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
                                    <span class='info'>
                                        <span>i</span>
                                        <p>Default: sendmail</p>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class='ralign'>Sendmail Path: </td>
                                <td>
                                    <input name='mail__sendmail' size='38' value='{{$configs['mail.sendmail']}}' readonly="readonly">
                                    <span class='info'>
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
                                    <span class='info'>
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
                                    <span class='info'>
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
                <div style="clear:both"></div>
                <div style='text-align: center'>
                    <input type='submit' value='Save Configs' class='button' />
                </div>
            </form>
        </div>
    </div>
@endsection

