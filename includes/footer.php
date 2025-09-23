<?php
// includes/footer.php
?>
<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
           <div class="footer-logo">
            <img src="assets/logo.png" alt="St. Francis Xavier Hospital Logo">
                <span>St. Francis Xavier Hospital</span>
            </div>
            <p class="footer-description">
                Providing quality healthcare services since May 31st, 1881. 
                Committed to excellence in patient care and employee well-being.
            </p>
            <div class="footer-social">
                <a href="https://www.facebook.com/Sisters-Hospitallers-103383643532609/" class="social-link"><i class="fab fa-facebook-f"></i></a>
                <a href="https://www.linkedin.com/company/sistershospitallers/" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                <a href="https://www.instagram.com/sistershospitallerscio/" class="social-link"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
        
        <div class="footer-section">
            <h4>Quick Links</h4>
            <ul class="footer-links">
                <li><a href="?page=help"><i class="fas fa-question-circle"></i> Help Center</a></li>
                <li><a href="?page=faq"><i class="fas fa-info-circle"></i> FAQ</a></li>
                <li><a href="?page=contact"><i class="fas fa-envelope"></i> Contact HR</a></li>
                <li><a href="?page=policy"><i class="fas fa-file-alt"></i> Leave Policy</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h4>Contact Info</h4>
            <div class="contact-info">
                <p><i class="fas fa-map-marker-alt"></i> PO Box 43 Assin-Foso, Ghana </p>
                <p><a href="https://www.google.com/maps/place/St.+Francis+Xavier+Hospital/@5.4906893,-0.7135283,17z/data=!3m1!4b1!4m6!3m5!1s0xfdf9b2f7e2f2d7d:0x8c6e3f5f8a5c6e0!8m2!3d5.4906844!4d-0.7113396!16s%2Fg%2F11c52_5_8y"><i class="fas fa-map-marked-alt"></i> 120 Mankessim - Kumasi Rd, Fosu</a></p>
                <p><i class="fas fa-globe"></i> www.stfrancishsc.org </p>
                <p><i class="fas fa-phone"></i> +233 24 493 4307 </p>
                <p><i class="fas fa-envelope"></i>  sisteric@stfrancishsc.org </p>
                <p><i class="fas fa-clock"></i> 24/7 </p>
            </div>
        </div>
        
        <div class="footer-section">
            <h4>System Info</h4>
            <div class="system-info">
                <p>ELMS Version: 1.0.0</p>
                <p>Last Updated: <?php echo date('F j, Y', strtotime('2025-09-21')); ?></p>
                <p>Server Time: <?php echo date('g:i A'); ?></p>
                <div class="status-indicator">
                    <span class="status-dot online"></span>
                    System Status: Online
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="footer-bottom-content">
            <p>&copy; <?php echo date('Y'); ?> St. Francis Xavier Hospital. All rights reserved.</p>
            <div class="footer-bottom-links">
                <a href="?page=privacy">Privacy Policy</a>
                <a href="?page=terms">Terms of Service</a>
                <a href="?page=accessibility">Accessibility</a>
            </div>
        </div>
    </div>
</footer>

<style>
/* Footer base - nice, modern, accessible */
.footer {
    background: linear-gradient(180deg, rgba(10,25,47,0.95), rgba(5,15,30,0.98));
    color: #e6eef8;
    margin-top: auto;
    padding-top: 24px;
    box-shadow: 0 -6px 24px rgba(2, 8, 23, 0.5);
    border-top: 1px solid rgba(255,255,255,0.03);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial;
}

/* Layout */
.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 28px;
    padding: 42px 20px;
    max-width: 1200px;
    margin: 0 auto;
    align-items: start;
}

/* Section styling */
.footer-section {
    padding: 4px 6px;
    transition: transform 0.28s ease, opacity 0.28s ease;
}
.footer-section:hover {
    transform: translateY(-4px);
    opacity: 0.98;
}

/* Headings */
.footer-section h4 {
    color: #8bd0ff;
    margin-bottom: 14px;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}

/* Logo area */
.footer-logo {
    width: 100%;
    height: auto;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 1.25rem;
    font-weight: 700;
}
.footer-logo img {
    width: 150px; height: 50px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: 0 6px 18px rgba(3, 16, 35, 0.6);
    border: 1px solid rgba(255,255,255,0.04);
}
.footer-logo span {
    color: #f4fbff;
    font-size: 1rem;
    line-height: 1;
}

/* Small utility */
:root {
    --footer-muted: rgba(230,238,248,0.78);
    --footer-quiet: rgba(230,238,248,0.64);
    --accent: #3ec7ff;
}
.footer-description {
    line-height: 1.6;
    margin-bottom: 18px;
    color: var(--footer-muted);
    font-size: 0.95rem;
}

/* Make social icons tidy */
.footer-social {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 6px;
}
.social-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: rgba(255,255,255,0.04);
    border-radius: 50%;
    color: #fff;
    text-decoration: none;
    transition: transform 0.22s ease, background-color 0.22s ease;
}
.social-link:hover,
.social-link:focus {
    background-color: var(--accent);
    transform: translateY(-3px) scale(1.03);
    outline: none;
}

/* Ensure lists and links are readable */
.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}
.footer-links li {
    margin-bottom: 10px;
}


.footer-links a {
    color: var(--footer-quiet);
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: start;
    padding-left: 75px;
    gap: 10px;
    transition: color 0.22s ease, transform 0.18s ease;
}
.contact-info a:hover,
.contact-info a:focus,
.footer-links a:hover,
.footer-links a:focus {
    color: var(--accent);
    transform: translateX(4px);
}

/* Contact & system info */
.contact-info p,
.system-info p {
    display: flex;
    align-items: center;
    justify-content: start;
    gap: 10px;
    padding-left: 20px;
    margin-bottom: 12px;
    color: var(--footer-quiet);
    font-size: 0.95rem;
}
.contact-info i,
.system-info i {
    color: var(--accent);
    width: 20px;
}

/* Visual status */
.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 8px;
    margin-left: 20px;
    width: fit-content;
    background-color: rgba(6, 132, 100, 0.08);
    border-radius: 6px;
    border-left: 3px solid rgba(60, 200, 140, 0.9);
}
.status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 4px rgba(60,200,140,0.12);
}
.status-dot.online { background-color: #3cdf9a; }

/* Footer bottom */
.footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.04);
    padding: 18px 0 28px;
    margin-top: 10px;
}
.footer-bottom-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    flex-wrap: wrap;
    gap: 12px;
}
.footer-bottom p {
    color: var(--footer-muted);
    margin: 0;
    font-size: 0.95rem;
}
.footer-bottom-links {
    display: flex;
    gap: 18px;
}
.footer-bottom-links a {
    color: var(--footer-quiet);
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.18s ease;
}
.footer-bottom-links a:hover,
.footer-bottom-links a:focus { color: var(--accent); }

/* Responsive adjustments */
@media (max-width: 768px) {
    .footer-content { padding: 28px 18px; grid-auto-rows: auto; }
    .footer-bottom-content { flex-direction: column; text-align: center; gap: 8px; }
    .contact-info p, .system-info p { justify-content: center; }
    .footer-logo { justify-content: center; }
    .footer-social { justify-content: center; }
}

@media (max-width: 420px) {
    .footer-content { padding: 20px 12px; gap: 18px; }
    .footer-logo img { width: 44px; height: 44px; }
    .footer-section h4 { font-size: 1rem; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update server time every minute
    function updateServerTime() {
        const timeElement = document.querySelector('.system-info p:nth-child(3)');
        if (timeElement) {
            const now = new Date();
            timeElement.innerHTML = `<i class="fas fa-clock"></i> Server Time: ${now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
        }
    }
    
    // Initial update
    updateServerTime();
    
    // Update every minute
    setInterval(updateServerTime, 60000);
    
    // Add current year to copyright if needed
    const yearElement = document.querySelector('footer p:first-child');
    if (yearElement && yearElement.textContent.includes('<?php echo date('Y'); ?>')) {
        yearElement.textContent = yearElement.textContent.replace('<?php echo date('Y'); ?>', new Date().getFullYear());
    }
});
</script>