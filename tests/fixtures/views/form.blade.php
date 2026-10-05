{{-- A page with a form, as sites write them: it reads the validation errors Laravel shares on web requests. --}}
<!doctype html><html><head><s:seo:meta /></head><body><h1>{{ $title }}</h1>@error('email')<p>{{ $message }}</p>@enderror</body></html>
