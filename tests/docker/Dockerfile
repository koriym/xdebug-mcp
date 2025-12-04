# Docker test environment for xdebug-mcp
# PHP 8.4 + Xdebug for integration testing

FROM php:8.4-cli

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Configure Xdebug for testing
# Note: xdebug.mode is set dynamically by xdebug-mcp tools
RUN echo 'zend_extension=xdebug.so\n\
xdebug.client_host=host.docker.internal\n\
xdebug.client_port=9004\n\
xdebug.start_with_request=trigger\n\
xdebug.output_dir=/tmp\n\
xdebug.use_compression=0' \
> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Create app directory
WORKDIR /app

# Copy test scripts
COPY *.php /app/

# Default command
CMD ["php", "-v"]
