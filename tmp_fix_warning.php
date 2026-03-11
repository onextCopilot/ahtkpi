<?php
$file = '/Users/hyuncao/AHT KPI/modules/debt_warning/index.php';
$content = file_get_contents($file);

// Remove the `<?php endif; ?>` that was causing syntax error
$content = preg_replace('/<\?php endif; \?>\s*<\ /div>\s*<\ /div>\s*<\ /main>\s*<\ /div>.*<\ /html>/is', "</div>\n</div>
                        \n</main>\n</div>\n</body>\n

                        </html>", $content);

                        file_put_contents($file, $content);
                        echo "Fixed syntax error\n";