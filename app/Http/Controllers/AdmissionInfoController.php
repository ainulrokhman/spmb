<?php

namespace App\Http\Controllers;

use App\Models\AdmissionWave;
use App\Models\GalleryPhoto;
use App\Models\SchoolUnit;
use App\Models\Setting;
use Illuminate\Support\Collection;

class AdmissionInfoController extends Controller
{
    /**
     * Public information page: the school units that currently open
     * registration, with their open waves.
     */
    public function index()
    {
        $units = $this->openUnits();

        return view('admissions.info', compact('units'));
    }

    /**
     * The public landing page: foundation branding, the gallery
     * slide, the units currently open for registration, the
     * admission steps, and frequently asked questions.
     */
    public function home()
    {
        $units = $this->openUnits();
        $photos = GalleryPhoto::shown()->get();
        $branding = [
            'name' => Setting::get('foundation_name'),
            'tagline' => Setting::get('foundation_tagline'),
            'logo' => Setting::get('site_logo'),
        ];
        $videoEmbed = $this->youtubeEmbedUrl(Setting::get('profile_video_url'));

        return view('home', compact('units', 'photos', 'branding', 'videoEmbed'));
    }

    /**
     * Active units that currently have at least one open wave,
     * each carrying its open waves as `open_waves`.
     *
     * @return Collection<int, SchoolUnit>
     */
    private function openUnits(): Collection
    {
        return SchoolUnit::query()
            ->with(['program', 'admissionWaves' => function ($query) {
                $query->with('academicYear')->orderBy('opens_at');
            }])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (SchoolUnit $unit) {
                $unit->setAttribute('open_waves', $unit->admissionWaves
                    ->filter(fn (AdmissionWave $wave) => $wave->status() === AdmissionWave::STATUS_OPEN)
                    ->values());

                return $unit;
            })
            ->filter(fn (SchoolUnit $unit) => $unit->getAttribute('open_waves')->isNotEmpty())
            ->values();
    }

    /**
     * Convert a YouTube watch / share / shorts URL into its
     * embed URL; null when the value is not a YouTube video.
     */
    private function youtubeEmbedUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~', $url, $m) === 1) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        return null;
    }
}
