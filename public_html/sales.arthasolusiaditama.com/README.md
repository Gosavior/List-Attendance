# Secure ReactJS Website dengan MySQL & Real-time

Struktur project untuk website aman dengan ReactJS, Tailwind CSS, MySQL backend, dan real-time capabilities menggunakan Socket.io.

## Struktur Project

```
├── client/                  # Frontend React Application
│   ├── src/
│   │   ├── components/      # React components
│   │   ├── pages/          # Page components
│   │   ├── services/       # API services
│   │   ├── hooks/          # Custom hooks
│   │   ├── utils/          # Utility functions
│   │   ├── context/        # Context providers
│   │   ├── App.jsx         # Main App component
│   │   └── main.jsx        # Entry point
│   ├── public/             # Static assets
│   └── package.json
│
└── server/                  # Backend Node.js Application
    ├── config/             # Configuration files
    │   └── database.js     # MySQL connection
    ├── controllers/        # Route controllers
    │   └── authController.js
    ├── models/             # Database models
    │   └── User.js
    ├── routes/             # API routes
    │   └── auth.js
    ├── middleware/         # Custom middleware
    │   ├── auth.js         # Authentication middleware
    │   └── validation.js   # Validation middleware
    ├── socket/             # Socket.io configuration
    │   └── index.js
    ├── utils/              # Utility functions
    │   └── helpers.js
    ├── database/           # Database schemas
    │   └── schema.sql
    ├── server.js           # Main server file
    ├── .env.example        # Environment variables template
    └── package.json
```

## Fitur Keamanan

- ✅ **Helmet.js** - Security headers
- ✅ **CORS** - Cross-Origin Resource Sharing protection
- ✅ **Rate Limiting** - Protect against DDoS attacks
- ✅ **JWT Authentication** - Secure token-based auth
- ✅ **Password Hashing** - bcryptjs for password security
- ✅ **Input Validation** - express-validator
- ✅ **HTTP-only Cookies** - Secure token storage

## Teknologi yang Digunakan

### Frontend
- React 18 (Vite)
- Tailwind CSS
- Socket.io Client

### Backend
- Node.js & Express
- MySQL2
- Socket.io
- JWT (jsonwebtoken)
- bcryptjs

## Setup & Installation

### 1. Setup Database MySQL

```sql
-- Buat database
CREATE DATABASE yourdatabase;

-- Gunakan database
USE yourdatabase;

-- Import schema
SOURCE server/database/schema.sql;
```

### 2. Setup Backend

```bash
# Masuk ke folder server
cd server

# Copy environment variables
copy .env.example .env

# Edit .env dengan konfigurasi MySQL Anda
# DB_HOST=localhost
# DB_USER=root
# DB_PASSWORD=yourpassword
# DB_NAME=yourdatabase
# JWT_SECRET=your-secret-key-here

# Install dependencies
npm install

# Jalankan server (development)
npm run dev

# Atau jalankan server (production)
npm start
```

Server akan berjalan di: http://localhost:5000

### 3. Setup Frontend

```bash
# Masuk ke folder client
cd client

# Install dependencies
npm install

# Jalankan development server
npm run dev

# Build untuk production
npm run build
```

Frontend akan berjalan di: http://localhost:5173

## API Endpoints

### Authentication

```
POST   /api/auth/register    # Register user baru
POST   /api/auth/login        # Login user
GET    /api/auth/me          # Get current user (Protected)
POST   /api/auth/logout       # Logout user (Protected)
```

### Health Check

```
GET    /health               # Check server status
```

## Socket.io Events

### Client to Server
- `join-room` - Join a room
- `leave-room` - Leave a room
- `message` - Send message to room
- `notification` - Send notification

### Server to Client
- `user-joined` - User joined room
- `user-left` - User left room
- `message` - Receive message
- `notification` - Receive notification

## Development

### Frontend Development

```bash
cd client
npm run dev
```

### Backend Development

```bash
cd server
npm run dev
```

## Catatan Penting

1. **Keamanan**
   - Jangan commit file `.env` ke git
   - Gunakan password yang kuat untuk JWT_SECRET
   - Selalu validasi input dari user
   - Gunakan HTTPS di production

2. **Database**
   - Backup database secara berkala
   - Gunakan prepared statements (sudah implemented)
   - Set proper indexes untuk performance

3. **Real-time**
   - Implement authentication untuk Socket.io
   - Handle reconnection logic di client
   - Validate semua socket events

## Next Steps

1. Lengkapi implementasi authentication di frontend
2. Buat components untuk Socket.io communication
3. Implement proper error handling
4. Add logging system
5. Setup testing (Jest, React Testing Library)
6. Configure production environment
7. Setup CI/CD pipeline

## Resources

- [Express Documentation](https://expressjs.com/)
- [React Documentation](https://react.dev/)
- [Socket.io Documentation](https://socket.io/)
- [Tailwind CSS Documentation](https://tailwindcss.com/)
- [MySQL2 Documentation](https://github.com/sidorares/node-mysql2)

## License

ISC
