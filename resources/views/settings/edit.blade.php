@extends('layouts.admin')


@section('content')
{{ Aire::open()->route('settings.update', $setting)->bind($setting)}}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar {{$setting->name}}</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>

            <div class="grid grid-cols-6 gap-6">
                @php
                    $help = '';
                    if($setting->id == 4){
                        $help = 'Hora militar';
                    }
                @endphp

                @if($setting->key === 'auto_updater_enabled')
                    <div class="col-span-6 sm:col-span-3">
                        {{ Aire::hidden('value')->value(0) }}
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="value" value="1" class="sr-only peer" @checked($setting->value == '1')>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-900">Habilitar</span>
                        </label>
                    </div>
                @elseif(in_array($setting->key, ['terms_conditions_content', 'privacy_policy_content', 'faq_content']))
                    <div class="col-span-6">
                        <label for="value" class="block text-sm font-medium text-gray-700 mb-2">{{ $setting->name }}</label>
                        <textarea 
                            id="rich-text-editor" 
                            name="value" 
                            rows="20" 
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                        >{{ old('value', $setting->value) }}</textarea>
                    </div>
                @elseif($setting->id == 5)
                    {{ Aire::textarea('value')->rows(10)->groupClass('col-span-6 sm:col-span-3') }}
                @else
                    {{ Aire::input('value')->helpText($help)->groupClass('col-span-6 sm:col-span-3') }}
                @endif
                
                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">

                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('settings.index') }}">Cancelar</a>
                    </p>

                               
                </div>
            </div>


        </div>
    </div>

   
</div>
{{ Aire::close() }}





@endsection

@push('head')
@if(in_array($setting->key, ['terms_conditions_content', 'privacy_policy_content', 'faq_content']))
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
@endif
@endpush

@section('scripts')
@if(in_array($setting->key, ['terms_conditions_content', 'privacy_policy_content', 'faq_content']))
<script>
tinymce.init({
    selector: '#rich-text-editor',
    height: 500,
    menubar: true,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
    language: 'es',
    branding: false,
    statusbar: true,
    resize: true,
    block_formats: 'Párrafo=p; Título 1=h1; Título 2=h2; Título 3=h3; Título 4=h4; Título 5=h5; Título 6=h6; Preformateado=pre',
    setup: function (editor) {
        editor.on('change', function () {
            editor.save();
        });
    }
});
</script>
@endif
@endsection
