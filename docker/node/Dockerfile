FROM node:20-alpine

WORKDIR /var/www/html

EXPOSE 5173

CMD ["sh", "-c", "npm install --legacy-peer-deps && npm run dev -- --host 0.0.0.0"]
