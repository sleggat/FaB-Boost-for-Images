# FaB Boost for Images - A Remote Image Processing Service in PHP

FaB Boost for Images is a lightweight, efficient image processing microservice designed to simplify remote image hosting for client sites. It provides a straightforward, URL-based API for hassle-free image transformations.

## Features

- On-the-fly image processing
- Secure domain whitelisting
- Image caching
- Support for multiple image formats (JPG, PNG, GIF, BMP, WebP, AVIF)
- Configurable image manipulations
- Cache-Control headers for optimal performance
- File size and dimension limits for security

## Dependencies

- PHP 8.1+
- Composer
- Required PHP extensions:
  - GD or ImageMagick
  - curl
  - fileinfo
- Composer packages:
  - league/glide
  - nyholm/psr7
  - laminas/laminas-httphandlerrunner

## Installation

1. Clone the repository:

```bash
git clone https://github.com/sleggat/fab-boost-for-images.git
```

2. Install dependencies:

```bash
composer install
```

3. Create required directories:

```bash
mkdir cache_local cache_remote
chmod 777 cache_local cache_remote
```

4. Configure your web server (example Nginx configuration included)

## Configuration

1. Update the `$whitelistedDomains` array in `image.php` to include your allowed domains:

```php
$whitelistedDomains = [
    'your-domain.com',
    'otherdomain.com'
];
```

2. Adjust size limits if needed:

```php
$maxFileSize = 10 * 1024 * 1024; // 10MB default
// Maximum dimensions
if ((isset($glideParams['w']) && $glideParams['w'] > 5000)...
```

## Usage

The service accepts URLs in the following format:

```
https://your-domain.com/image/example.com/path/to/image.jpg
```

### Parameters

Add image manipulation parameters as query strings:

| Parameter | Description | Values | Example |
|-----------|-------------|--------|---------|
| `w` | Width in pixels | Any positive integer | `w=800` |
| `h` | Height in pixels | Any positive integer | `h=600` |
| `q` | Quality | `0` to `100` (default varies by format) | `q=80` |
| `blur` | Blur effect | `0` to `100` | `blur=10` |
| `sharp` | Sharpening | `0` to `100` | `sharp=15` |
| `fm` | Format conversion | `jpg`, `png`, `gif`, `webp`, `avif` | `fm=webp` |
| `crop` | Crop mode | `crop-center`, `crop-top`, `crop-bottom`, `crop-left`, `crop-right`, `crop-top-left`, `crop-top-right`, `crop-bottom-left`, `crop-bottom-right` (requires `w` and `h`) | `crop=crop-center` |
| `bri` | Brightness | `-100` to `100` | `bri=50` |
| `con` | Contrast | `-100` to `100` | `con=25` |
| `gam` | Gamma | `0.1` to `9.99` | `gam=2` |
| `flip` | Flip image | `v` (vertical), `h` (horizontal), `both` | `flip=v` |
| `or` | Orientation | `0`, `90`, `180`, `270`, `auto` | `or=90` |
| `bg` | Background colour | 3, 4, 6, or 8 character hex (without `#`) | `bg=ff0000` |
| `purge` | Clear cached versions | `1` | `purge=1` |

Parameters can be combined:

```
https://your-domain.com/image/example.com/image.jpg?w=800&h=600&q=80&fm=webp
```

## Security

The service currently includes several security measures:

- Domain whitelisting
- File size limits (10MB default)
- Dimension limits (5000px default)
- File type verification
- Input sanitization
- Secure file handling

## Caching

Images are cached in two layers:

1. Original downloaded images in the `cache_remote/` directory
2. Processed images in the `cache_local/` directory

## Notes

1. This is a fairly new project and I wouldn't recommend using this in production, yet.
2. You might want to implement more rigorous checks or disable CPU intensive processes like blur.

Contact me at hello@frontandback.co.nz
https://frontandback.co.nz
