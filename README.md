# Laravel Web Scraping API

This Laravel project serves as a web scraping API designed to retrieve movie information from a selected Polish cinema website. It is currently at version 1, providing basic functionality for fetching movie data.

## Features

- Web scraping functionality to extract movie information
- API endpoints for accessing movie data
- Support for a selected Polish cinema website

## Installation

To set up the Laravel Web Scraping API on your local machine, follow these steps:

1. Clone this repository to your local environment.
2. Navigate to the project directory.
3. Run `composer install` to install dependencies.
4. Configure your environment variables in the `.env` file.
5. Run `php artisan serve` to start the development server.

### API Endpoints

Here are the available API endpoints:

- `GET /api/backup`: Trigger backup of data.
- `GET /api/dates`: Get a list of dates in advance.
- `GET /api/movies-1`: Retrieve movies from CinemaCity.
- `GET /api/movies-2`: Retrieve movies from Multikino.
- `GET /api/movies-3`: Retrieve movies from KinoMoranow.
- `GET /api/locations`: Get a list of cinema cities.
- `GET /api/attributes`: Get a list of attributes.
- `GET /api/sync`: Trigger synchronization.
- `POST /api/movies/search`: Search movies by location.
- `POST /api/movies`: Store a new movie.
- `DELETE /api/movies/{id}`: Delete a movie by ID.
- `GET /api/cinemas`: Retrieve a list of cinemas.
- `POST /api/cinemas`: Store a new cinema.
- `DELETE /api/cinemas/{id}`: Delete a cinema by ID.

Note: Some endpoints may require authentication.

## Technologies Used

This project is built using Laravel, a PHP web application framework. Additionally, it leverages web scraping techniques to extract data from the selected Polish cinema website.

## Version History

- **Version 1**
  - Initial release with basic web scraping functionality.

## Contributing

Contributions to this project are welcome! If you'd like to contribute, please fork the repository, make your changes, and submit a pull request.

## License

This project is licensed under the [MIT License](LICENSE).
