# Imagem do serviço `node` (Vite, build e testes do frontend).
#
# O node_modules fica em um volume nomeado, fora do bind mount: ler milhares de
# arquivos pelo bind mount do Windows/macOS deixava o Vite e o Vitest muito
# lentos. O diretório é criado aqui com dono `node` porque um volume nomeado
# novo herda a posse do diretório da imagem; sem isso ele nasceria como root e
# o `npm install` (executado como `node`) falharia.

FROM node:22-alpine

RUN mkdir -p /var/www/html/node_modules && chown -R node:node /var/www/html

WORKDIR /var/www/html

USER node
