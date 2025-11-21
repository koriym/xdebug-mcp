FROM php:8.4-cli

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Configure Xdebug for testing
RUN echo 'zend_extension=xdebug.so\n\
xdebug.mode=${XDEBUG_MODE:-debug}\n\
xdebug.client_host=host.docker.internal\n\
xdebug.client_port=9003\n\
xdebug.start_with_request=trigger\n\
xdebug.output_dir=/tmp\n\
xdebug.use_compression=0\n\
xdebug.trace_format=1' \
> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /app

# Copy test scripts
COPY test_script.php /app/
COPY test_symfony_like.php /app/
COPY test_coverage.php /app/

CMD ["php", "-v"]
