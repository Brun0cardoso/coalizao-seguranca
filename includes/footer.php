<?php $siteConfig = require __DIR__ . '/../php/config.php'; ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="footer-logo" href="index.php" aria-label="Coalizão Segurança - início"><img src="assets/images/logo.png" alt="Coalizão Segurança"><span>Coalizão<br><strong>Segurança</strong></span></a>
            <p>Proteção profissional para pessoas, patrimônios e operações.</p>
            <?php if (!empty($siteConfig['social_links']['instagram'])): ?><a class="footer-instagram" href="<?= htmlspecialchars($siteConfig['social_links']['instagram'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Siga a Coalizão no Instagram</a><?php endif; ?>
        </div>
        <nav class="footer-navigation" aria-label="Navegação do rodapé">
            <h2>Explore</h2>
            <a href="index.php#quem-somos">Quem somos</a>
            <a href="index.php#servicos">Serviços</a>
            <a href="index.php#diferenciais">Diferenciais</a>
            <a href="index.php#contato">Contato</a>
        </nav>
        <div class="footer-contact">
            <h2>Fale conosco</h2>
            <?php if (!empty($siteConfig['company_email'])): ?><a href="mailto:<?= htmlspecialchars($siteConfig['company_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($siteConfig['company_email'], ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
            <?php if (!empty($siteConfig['company_address'])): ?><p><?= htmlspecialchars($siteConfig['company_address'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <?php if (!empty($siteConfig['whatsapp_number'])): ?><a class="footer-whatsapp-link" href="https://wa.me/<?= preg_replace('/\D+/', '', $siteConfig['whatsapp_number']) ?>?text=<?= rawurlencode('Olá! Gostaria de solicitar um orçamento.') ?>" target="_blank" rel="noopener noreferrer">Conversar pelo WhatsApp <span aria-hidden="true">↗</span></a><?php endif; ?>
        </div>
        <div class="footer-actions">
            <?php $socialNames = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn']; ?>
            <?php if (array_filter($siteConfig['social_links'])): ?>
                <nav class="social-links" aria-label="Redes sociais">
                    <?php foreach ($socialNames as $network => $label): ?>
                        <?php if (!empty($siteConfig['social_links'][$network])): ?>
                            <a class="social-link social-link--<?= $network ?>" href="<?= htmlspecialchars($siteConfig['social_links'][$network], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $label ?>">
                                <?php if ($network === 'instagram'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg><?php endif; ?>
                                <?php if ($network === 'facebook'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h3V4h-3c-3.3 0-5 2-5 5v3H6v4h3v4h4v-4h3l1-4h-4V9c0-.7.3-1 1-1Z"/></svg><?php endif; ?>
                                <?php if ($network === 'linkedin'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8.5H3V21h3.5V8.5ZM4.75 3A2.05 2.05 0 1 0 4.8 7.1 2.05 2.05 0 0 0 4.75 3ZM21 13.8c0-3.8-2-5.6-4.7-5.6-2.2 0-3.2 1.2-3.8 2v-1.7H9V21h3.5v-6.2c0-1.6.3-3.2 2.3-3.2 2 0 2 1.9 2 3.3V21H21v-7.2Z"/></svg><?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    <div class="container footer-bottom"><p>&copy; <?= date('Y') ?> Coalizão Segurança. Todos os direitos reservados.</p><p class="developer-credit">Desenvolvido por <a href="<?= htmlspecialchars($siteConfig['developer_github'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Bruno Cardoso</a></p><a class="admin-access" href="login.php">Acesso ao painel administrativo</a></div>
</footer>
<?php if (!empty($siteConfig['whatsapp_number'])): ?>
    <?php $whatsappNumber = preg_replace('/\D+/', '', $siteConfig['whatsapp_number']); ?>
    <a class="whatsapp-float" href="https://wa.me/<?= $whatsappNumber ?>?text=<?= rawurlencode('Olá! Gostaria de solicitar um orçamento.') ?>" target="_blank" rel="noopener noreferrer" aria-label="Falar conosco pelo WhatsApp">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 3.5A11.8 11.8 0 0 0 2.8 19.2L2 22l2.9-.8A11.8 11.8 0 1 0 20.5 3.5ZM12 21a9 9 0 0 1-4.6-1.3l-.3-.2-1.7.5.5-1.7-.2-.3A9 9 0 1 1 12 21Zm4.9-6.7c-.3-.1-1.7-.8-2-.9s-.5-.1-.7.2-.8.9-.9 1.1-.3.2-.6.1a7.3 7.3 0 0 1-2.2-1.4 8.1 8.1 0 0 1-1.5-1.9c-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.1c-.2-.5-.5-.4-.7-.4h-.6c-.2 0-.5.1-.8.4s-1 1-1 2.4 1 2.8 1.1 3 2 3.2 4.8 4.5c.7.3 1.2.5 1.7.7.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.4s.2-1.3.1-1.4c-.1-.2-.3-.2-.6-.4Z"/></svg>
    </a>
<?php endif; ?>
<script src="js/menu.js"></script>
<script src="js/script.js"></script>
</body>
</html>
