# PHP Image Processing Service

A secure and efficient image processing microservice that remote downloads, caches, and manipulates images on the fly. Built with PHP and the League's Glide library, it provides a simple URL-based API for image transformations.

## Features

- On-the-fly image processing
- Secure domain whitelisting
- Image caching
- Support for multiple image formats (JPG, PNG, GIF, BMP, WebP)
- Configurable image manipulations
- Cache-Control headers for optimal performance
- File size and dimension limits for security

## Dependencies

- PHP 7.4+
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
git clone https://github.com/sleggat/imgcdn.git
```

2. Install dependencies:

```bash
composer install
```

3. Create required directories:

```bash
mkdir cache remote
chmod 777 cache remote
```

4. Configure your web server (example Nginx configuration included)

## Configuration

1. Update the `$whitelistedDomains` array in `image.php` to include your allowed domains:

```php
$whitelistedDomains = [
    'yourdomain.com',
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

- `w`: Width
- `h`: Height
- `q`: Quality (0-100)
- `blur`: Blur effect
- `sharp`: Sharpening
- `fm`: Format conversion
- `crop`: Crop mode
- `bri`: Brightness
- `con`: Contrast
- `gam`: Gamma
- `flip`: Flip image
- `or`: Orientation

Example:

```
https://your-domain.com/image/example.com/image.jpg?w=800&h=600&q=80
```

## Security

The service includes several security measures:

- Domain whitelisting
- File size limits (10MB default)
- Dimension limits (5000px default)
- File type verification
- Input sanitization
- Secure file handling

## Caching

Images are cached in two layers:

1. Original downloaded images in the `remote/` directory
2. Processed images in the `cache/` directory

Cache-Control headers are set to:

- max-age: 8640000
- s-maxage: 31536000
- stale-while-revalidate: 86400
- stale-if-error: 86400

## Nginx Configuration

Include the provided Nginx configuration for optimal performance and security. Key features:

- SSL support
- Proper routing for image processing
- Static file handling
- PHP-FPM configuration
- Cache header handling

I wouldn't recommend using this in production, yet.
