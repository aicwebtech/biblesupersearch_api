@php
    //$javascripts = ['/js/bin/ckeditor/ckeditor.js'];
    $javascripts = ['/js/bin/ckeditor5/build/ckeditor.js'];
    // $javascripts = ['https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js'];
    // $javascripts = ['https://cdn.ckeditor.com/ckeditor5/39.0.1/super-build/ckeditor.js'];
    // $javascripts = ['/js/bin/ckeditor_5_39/ckeditor.js'];
@endphp

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <div class='content'>
            <h2 class='title'>{{$Post->title}}</h2>
            <form method='POST'>
                <?php echo csrf_field() ?>
                <h5>Please specify your {{$Post->title}} here.  It will appear on the documentation page.</h5>
                <textarea name='content' id='content'>{{$Post->content}}</textarea>
                <script>
                    // CKEDITOR.replace('content', {
                    //     height: 400
                    // });

                        ClassicEditor
                            .create( document.querySelector( '#content' ), {
                                height: 300,
                                width: 1200,
                                link: {
                                    decorators: {
                                        openInNewTab: {
                                            mode: 'manual',
                                            label: 'Open in a new tab',
                                            attributes: {
                                                target: '_blank',
                                                rel: 'noopener noreferrer'
                                            }
                                        }
                                    }
                                },
                                // toolbar: {
                                //     items: [
                                //         'exportPDF','exportWord', '|',
                                //         'findAndReplace', 'selectAll', '|',
                                //         'heading', '|',
                                //         'bold', 'italic', 'strikethrough', 'underline', 'code', 'subscript', 'superscript', 'removeFormat', '|',
                                //         'bulletedList', 'numberedList', 'todoList', '|',
                                //         'outdent', 'indent', '|',
                                //         'undo', 'redo',
                                //         '-',
                                //         'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', 'highlight', '|',
                                //         'alignment', '|',
                                //         'link', 'insertImage', 'blockQuote', 'insertTable', 'mediaEmbed', 'codeBlock', 'htmlEmbed', '|',
                                //         'specialCharacters', 'horizontalLine', 'pageBreak', '|',
                                //         'textPartLanguage', '|',
                                //         'sourceEditing'
                                //     ],
                                //     shouldNotGroupWhenFull: true
                                // },
                            } )
                            .catch( error => {
                                console.error( error );
                            } );
                </script>

                <br /><br />
                <input type='submit' value='Save' class='button' />
            </form>
        </div>
    </div>
@endsection

