@php
    use ClassyFashion\Support\Storefront;
@endphp

<x-admin::layouts>
    <x-slot:title>{{ trans('classy-fashion::storefront.editor_title') }}</x-slot>

    <div class="cfh-editor">
        <div class="cfh-editor-heading">
            <div><h1>{{ trans('classy-fashion::storefront.editor_title') }}</h1><p>{{ trans('classy-fashion::storefront.editor_help') }}</p></div>
            <a href="{{ route('shop.home.index') }}" target="_blank" rel="noopener" class="secondary-button">{{ trans('classy-fashion::storefront.view_shop') }}</a>
        </div>

        @if ($errors->any())
            <div role="alert" class="cfh-editor-errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('admin.classy.storefront.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @foreach (['hero' => ['hero_title', 'hero_text', 'hero_button', 'hero_link', 'hero_image', 'hero_alt'], 'collections' => ['collection_title', 'collection_text', 'dresses_title', 'dresses_link', 'dresses_image', 'dresses_alt', 'caps_title', 'caps_link', 'caps_image', 'caps_alt', 'everyday_title', 'everyday_link', 'everyday_image', 'everyday_alt'], 'options' => ['assistant_enabled']] as $group => $fields)
                <fieldset>
                    <legend>{{ trans('classy-fashion::storefront.groups.'.$group) }}</legend>
                    <div class="cfh-editor-fields">
                        @foreach ($fields as $field)
                            @php ($type = Storefront::fields()[$field])
                            <div class="cfh-editor-field">
                                <label for="{{ $field }}">{{ trans('classy-fashion::storefront.fields.'.$field) }}</label>
                                @if ($type === 'boolean')
                                    <input type="hidden" name="{{ $field }}" value="0">
                                    <input type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1" @checked(old($field, Storefront::get($field)))>
                                @elseif ($type === 'image')
                                    @if (Storefront::image($field))
                                        <img src="{{ Storefront::image($field) }}" alt="{{ trans('classy-fashion::storefront.current_photo') }}" width="240" height="180">
                                    @endif
                                    <input type="file" id="{{ $field }}" name="{{ $field }}" accept="image/jpeg,image/png,image/webp" aria-describedby="{{ $field }}-help">
                                    <small id="{{ $field }}-help">{{ trans('classy-fashion::storefront.image_help') }}</small>
                                @elseif ($type === 'textarea')
                                    <textarea id="{{ $field }}" name="{{ $field }}" rows="3" maxlength="1000" required>{{ old($field, Storefront::get($field)) }}</textarea>
                                @else
                                    <input type="text" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, Storefront::get($field)) }}" maxlength="{{ $type === 'link' ? 500 : 200 }}" required @if($type === 'link') aria-describedby="{{ $field }}-help" @endif>
                                    @if ($type === 'link')<small id="{{ $field }}-help">{{ trans('classy-fashion::storefront.link_help') }}</small>@endif
                                @endif
                                @error($field)<p role="alert">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            <div class="cfh-editor-save"><p>{{ trans('classy-fashion::storefront.publish_help') }}</p><button type="submit" class="primary-button">{{ trans('classy-fashion::storefront.publish') }}</button></div>
        </form>
    </div>
</x-admin::layouts>
