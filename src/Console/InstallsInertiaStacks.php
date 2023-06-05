<?php

namespace Laravel\Breeze\Console;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

trait InstallsInertiaStacks
{
    /**
     * Install the Inertia Svelte Breeze stack. Courtesy of @1CAMOU
     *
     * @return int|null
     */
    protected function installInertiaSvelteStack()
    {
        // Install Inertia...
        if (!$this->requireComposerPackages('inertiajs/inertia-laravel:^0.6.8', 'laravel/sanctum:^3.2', 'tightenco/ziggy:^1.0')) {
            return 1;
        }

        // NPM Packages...
        $this->updateNodePackages(function ($packages) {
            return [
                '@inertiajs/svelte' => '^1.0.0',
                '@tailwindcss/forms' => '^0.5.3',
                '@sveltejs/vite-plugin-svelte' => '^2.0.2',
                'autoprefixer' => '^10.4.12',
                'postcss' => '^8.4.18',
                'tailwindcss' => '^3.2.1',
                'svelte' => '^3.55.1',
            ] + $packages;
        });

        // Controllers...
        (new Filesystem)->ensureDirectoryExists(app_path('Http/Controllers'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/inertia-common/app/Http/Controllers', app_path('Http/Controllers'));

        // Requests...
        (new Filesystem)->ensureDirectoryExists(app_path('Http/Requests'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/default/app/Http/Requests', app_path('Http/Requests'));

        // Middleware...
        $this->installMiddlewareAfter('SubstituteBindings::class', '\App\Http\Middleware\HandleInertiaRequests::class');
        $this->installMiddlewareAfter('\App\Http\Middleware\HandleInertiaRequests::class', '\Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class');

        copy(__DIR__ . '/../../stubs/inertia-common/app/Http/Middleware/HandleInertiaRequests.php', app_path('Http/Middleware/HandleInertiaRequests.php'));

        // Views...
        copy(__DIR__ . '/../../stubs/inertia-svelte/resources/views/app.blade.php', resource_path('views/app.blade.php'));

        // Components + Pages...
        (new Filesystem)->ensureDirectoryExists(resource_path('js/Components'));
        (new Filesystem)->ensureDirectoryExists(resource_path('js/Layouts'));
        (new Filesystem)->ensureDirectoryExists(resource_path('js/Pages'));

        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/inertia-svelte/resources/js/Components', resource_path('js/Components'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/inertia-svelte/resources/js/Layouts', resource_path('js/Layouts'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/inertia-svelte/resources/js/Pages', resource_path('js/Pages'));

        if (!$this->option('dark')) {
            $this->removeDarkClasses((new Finder)
                    ->in(resource_path('js'))
                    ->name('*.svelte')
                    ->notName('Welcome.svelte')
            );
        }

        // Tests...
        $this->installTests();
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/inertia-common/tests/Feature', base_path('tests/Feature'));

        // Routes...
        copy(__DIR__ . '/../../stubs/inertia-common/routes/web.php', base_path('routes/web.php'));
        copy(__DIR__ . '/../../stubs/inertia-common/routes/auth.php', base_path('routes/auth.php'));

        // "Dashboard" Route...
        $this->replaceInFile('/home', '/dashboard', resource_path('js/Pages/Welcome.svelte'));
        $this->replaceInFile('Home', 'Dashboard', resource_path('js/Pages/Welcome.svelte'));
        $this->replaceInFile('/home', '/dashboard', app_path('Providers/RouteServiceProvider.php'));

        // Tailwind / Vite / Ziggy...
        copy(__DIR__ . '/../../stubs/default/resources/css/app.css', resource_path('css/app.css'));
        copy(__DIR__ . '/../../stubs/default/postcss.config.js', base_path('postcss.config.js'));
        copy(__DIR__ . '/../../stubs/inertia-common/tailwind.config.js', base_path('tailwind.config.js'));
        copy(__DIR__ . '/../../stubs/inertia-common/jsconfig.json', base_path('jsconfig.json'));
        copy(__DIR__ . '/../../stubs/inertia-svelte/vite.config.js', base_path('vite.config.js'));
        copy(__DIR__ . '/../../stubs/inertia-svelte/resources/js/app.js', resource_path('js/app.js'));
        copy(__DIR__ . '/../../stubs/inertia-svelte/resources/js/ziggy.js', resource_path('js/ziggy.js'));

        rename(base_path('tailwind.config.js'), base_path('tailwind.config.cjs'));
        $this->replaceInFile('.vue', '.svelte', base_path('tailwind.config.cjs'));

        // CommonJS...
        $packageJson = explode("\n", (new Filesystem)->get(base_path('package.json')));
        array_splice($packageJson, 1, 0, ['    "type": "module",']);
        $updatedPackageJson = implode("\n", $packageJson);
        (new Filesystem)->put(base_path('package.json'), $updatedPackageJson);

        if (file_exists(base_path('postcss.config.js'))) {
            rename(base_path('postcss.config.js'), base_path('postcss.config.cjs'));
        } else {
            copy(__DIR__ . '/../../stubs/inertia-svelte/postcss.config.cjs', base_path('postcss.config.cjs'));
        }

        if ($this->option('ssr')) {
            $this->installInertiaSvelteSsrStack();
        }

        $this->components->info('Installing and building Node dependencies.');

        if (file_exists(base_path('pnpm-lock.yaml'))) {
            $this->runCommands(['pnpm install', 'pnpm run build']);
        } elseif (file_exists(base_path('yarn.lock'))) {
            $this->runCommands(['yarn install', 'yarn run build']);
        } else {
            $this->runCommands(['npm install', 'npm run build']);
        }

        $this->line('');
        $this->components->info('Breeze scaffolding installed successfully.');
    }

    /**
     * Install the Inertia Svelte SSR stack into the application.
     *
     * @return void
     */
    protected function installInertiaSvelteSsrStack()
    {
        copy(__DIR__ . '/../../stubs/inertia-svelte/resources/js/ssr.js', resource_path('js/ssr.js'));
        $this->replaceInFile("input: 'resources/js/app.js',", "input: 'resources/js/app.js'," . PHP_EOL . "            ssr: 'resources/js/ssr.js',", base_path('vite.config.js'));
        $this->replaceInFile('vite build', 'vite build && vite build --ssr', base_path('package.json'));
        $this->replaceInFile('/node_modules', '/bootstrap/ssr' . PHP_EOL . '/node_modules', base_path('.gitignore'));
    }
}
