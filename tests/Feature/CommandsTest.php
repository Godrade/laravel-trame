<?php

use Illuminate\Support\Facades\File;

it('generates a block class and view', function () {
    $dir = sys_get_temp_dir().'/trame-blocks-'.uniqid();
    config(['trame.blocks.discover.path' => $dir, 'trame.blocks.discover.namespace' => 'App\\Trame\\Blocks']);

    $this->artisan('make:trame-block', ['name' => 'Testimonial'])->assertSuccessful();

    expect(File::get("{$dir}/TestimonialBlock.php"))
        ->toContain('namespace App\\Trame\\Blocks;')
        ->toContain("public static string \$type = 'testimonial';")
        ->toContain("return 'trame.blocks.testimonial';");

    expect(File::exists(resource_path('views/trame/blocks/testimonial.blade.php')))->toBeTrue();

    $this->artisan('make:trame-block', ['name' => 'Testimonial'])->assertFailed();

    File::deleteDirectory($dir);
    File::deleteDirectory(resource_path('views/trame'));
});

it('writes tailwind sources for the palette', function () {
    $path = sys_get_temp_dir().'/trame-palette-'.uniqid().'.css';
    config(['trame.palette' => ['white', 'slate-900', 'bad class']]);

    $this->artisan('trame:sources', ['--path' => $path])->assertSuccessful();

    expect(File::get($path))->toContain('@source inline("{text,bg,border}-{white,slate-900}");');

    File::delete($path);
});
