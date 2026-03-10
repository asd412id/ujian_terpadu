<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use App\Models\PaketUjian;

// Repositories
use App\Repositories\SoalRepository;
use App\Repositories\PaketUjianRepository;
use App\Repositories\SekolahRepository;
use App\Repositories\KategoriSoalRepository;
use App\Repositories\UserRepository;
use App\Repositories\PesertaRepository;
use App\Repositories\MonitoringRepository;
use App\Repositories\LaporanRepository;
use App\Repositories\GradingRepository;
use App\Repositories\SesiUjianRepository;
use App\Repositories\JawabanRepository;
use App\Repositories\AuthRepository;

// Services
use App\Services\SoalService;
use App\Services\PaketUjianService;
use App\Services\SekolahService;
use App\Services\KategoriSoalService;
use App\Services\UserService;
use App\Services\PesertaService;
use App\Services\MonitoringService;
use App\Services\LaporanService;
use App\Services\GradingService;
use App\Services\UjianService;
use App\Services\JawabanService;
use App\Services\LobbyService;
use App\Services\AuthService;
use App\Services\KartuLoginService;
use App\Services\DashboardService;
use App\Services\PenilaianService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Repositories are auto-resolved by Laravel's container
        // since they use constructor injection with concrete model classes.
        // No explicit bindings needed for repositories.

        // Services are also auto-resolved since they depend on
        // concrete repository classes (not interfaces).
        // Laravel's container handles the dependency chain automatically:
        // Controller -> Service -> Repository -> Model
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Paginator::defaultView('components.pagination');

        Route::bind('paket_trashed', function (string $value) {
            return PaketUjian::withTrashed()->where('id', $value)->firstOrFail();
        });
    }
}
