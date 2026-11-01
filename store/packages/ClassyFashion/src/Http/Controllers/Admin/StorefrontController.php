<?php

namespace ClassyFashion\Http\Controllers\Admin;

use ClassyFashion\Repositories\StorefrontRepository;
use ClassyFashion\Support\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * Plain-language homepage editing for the shop owner.
 */
class StorefrontController extends Controller
{
    /**
     * Show current published content.
     */
    public function index(): View
    {
        abort_unless(bouncer()->hasPermission('settings.storefront'), 401);

        return view('classy-fashion::admin.storefront.index');
    }

    /**
     * Validate and publish only the owner-facing content fields.
     */
    public function update(Request $request, StorefrontRepository $configs): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('settings.storefront'), 401);

        $rules = [];

        foreach (Storefront::fields() as $field => $type) {
            $rules[$field] = match ($type) {
                'boolean' => ['required', 'boolean'],
                'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
                'link' => ['required', 'string', 'max:500'],
                'textarea' => ['required', 'string', 'max:1000'],
                default => ['required', 'string', 'max:200'],
            };
        }

        $data = $request->validate($rules);

        foreach (Storefront::fields() as $field => $type) {
            if ($type === 'link' && ! Storefront::validLink($data[$field])) {
                throw ValidationException::withMessages([$field => trans('classy-fashion::storefront.invalid_link')]);
            }
        }

        $uploads = [];

        try {
            foreach (Storefront::fields() as $field => $type) {
                if ($type !== 'image') {
                    continue;
                }

                if ($request->hasFile($field)) {
                    $uploads[$field] = $request->file($field)->store('storefront', 'public');
                    $data[$field] = $uploads[$field];
                } else {
                    unset($data[$field]);
                }
            }

            $configs->publish([
                'channel' => core()->getCurrentChannelCode(),
                'locale' => app()->getLocale(),
                'general' => ['content' => ['storefront' => $data]],
            ]);
        } catch (\Throwable $exception) {
            foreach ($uploads as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        session()->flash('success', trans('classy-fashion::storefront.saved'));

        return redirect()->route('admin.classy.storefront.index');
    }
}
