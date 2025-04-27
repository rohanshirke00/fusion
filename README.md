# Fusion App - Video Merging with Vue.js & Laravel

**Fusion App** is a web application for merging multiple videos into one using a **Vue.js frontend** and a **Laravel backend**. It offers a user-friendly interface and efficient video processing.

## Project Structure

- **`fusion-app/`**: Vue.js frontend (client-side).
- **`fusion-api/`**: Laravel backend (server-side).

## Key Features

- **Video Merging**: Combine multiple videos into one.
- **Intuitive UI**: Built with **Vue.js** for a smooth experience.
- **Efficient Backend**: Laravel API for seamless video processing.
- **Cross-Platform**: Works across all modern browsers and devices.

## Technologies Used

- **Frontend**: Vue.js
- **Backend**: Laravel
- **Database**: MySQL/SQLite

## Prerequisites

Ensure you have these installed:
- **Node.js** (Frontend)
- **Composer** (Backend)
- **MySQL/SQLite** (Database)

## Setup

1. **Clone the repository**:
    ```bash
    git clone
    ```

2. **Frontend Setup**:
    ```bash
    cd fusion-app
    npm install
    npm run dev
    ```

3. **Backend Setup**:
    ```bash
    cd fusion-api
    composer install
    cp .env.example .env
    php artisan migrate
    php artisan serve
    ```
    
## Created by

[Rohan Shirke](https://github.com/rohanshirke00)
