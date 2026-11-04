<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Landing page gallery management: officers upload activity
 * photos, order them, toggle their visibility, and remove
 * them. Only active photos reach the public carousel.
 */
class GalleryController extends Controller
{
    public function index(): View
    {
        $photos = GalleryPhoto::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.gallery.index', compact('photos'));
    }

    /**
     * Upload one or more new photos (appended at the end of
     * the current order).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:200'],
        ]);

        $nextOrder = (int) GalleryPhoto::query()->max('sort_order');

        foreach ($request->file('photos') as $file) {
            GalleryPhoto::create([
                'file_path' => $file->store('galeri', 'public'),
                'caption' => $data['caption'] ?? null,
                'sort_order' => ++$nextOrder,
                'is_active' => true,
            ]);
        }

        return redirect()->route('admin.gallery.index')
            ->with('success', 'Foto galeri berhasil ditambahkan.');
    }

    /**
     * Update one photo's caption, order, and visibility.
     */
    public function update(Request $request, GalleryPhoto $photo): RedirectResponse
    {
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:200'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $photo->update([
            'caption' => $data['caption'] ?? null,
            'sort_order' => $data['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.gallery.index')
            ->with('success', 'Foto galeri berhasil diperbarui.');
    }

    /**
     * Delete one photo and its stored file.
     */
    public function destroy(GalleryPhoto $photo): RedirectResponse
    {
        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();

        return redirect()->route('admin.gallery.index')
            ->with('success', 'Foto galeri berhasil dihapus.');
    }
}
