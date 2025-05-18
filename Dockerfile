FROM php:7.4-apache

# 複製程式碼到 Apache 的 Web 根目錄
COPY public/ /var/www/html/

# 設置正確的權限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 啟用 Apache rewrite 模組
RUN a2enmod rewrite

# 配置 Apache，設置預設索引檔案並禁用目錄索引
RUN echo "<Directory /var/www/html>\n\
    Options -Indexes\n\
    AllowOverride All\n\
    Require all granted\n\
    DirectoryIndex index.php bot.php\n\
</Directory>" > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

# 暴露端口
EXPOSE 80

# 啟動 Apache
CMD ["apache2-foreground"]
