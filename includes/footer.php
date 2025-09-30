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
                <p><a href="https://www.google.com/maps/place/St.+Francis+Xavier+Hospital/@5.4906893,-0.7135283,17z" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-marker-alt"></i> PO Box 43 Assin-Foso, Ghana</a></p>
                <p><a href="https://www.google.com/maps/place/St.+Francis+Xavier+Hospital/@5.4906893,-0.7135283,17z/data=!3m1!4b1!4m6!3m5!1s0xfdf9b2f7e2f2d7d:0x8c6e3f5f8a5c6e0!8m2!3d5.4906844!4d-0.7113396!16s%2Fg%2F11c52_5_8y" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-marked-alt"></i> 120 Mankessim - Kumasi Rd, Fosu</a></p>
                <p><a href="https://www.stfrancishsc.org" target="_blank" rel="noopener noreferrer"><i class="fas fa-globe"></i> www.stfrancishsc.org</a></p>
                <p><a href="tel:+233244934307"><i class="fas fa-phone"></i> +233 24 493 4307</a></p>
                <p><a href="mailto:sisteric@stfrancishsc.org"><i class="fas fa-envelope"></i> sisteric@stfrancishsc.org</a></p>
                <p><i class="fas fa-clock"></i> 24/7</p>
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
/* Footer base - professional, modern, accessible */
:root {
    --primary-color: #8c2d3c;
    --primary-dark: #7a2433;
    --primary-light: #a13546;
    --footer-bg-start: rgba(25, 30, 45, 0.98);
    --footer-bg-end: rgba(15, 20, 35, 0.99);
    --footer-text: #f0f4f8;
    --footer-muted: rgba(240, 244, 248, 0.85);
    --footer-quiet: rgba(240, 244, 248, 0.7);
    --accent: #a13546;
    --accent-hover: #b84556;
    --success-color: #2ecc71;
    --border-color: rgba(255, 255, 255, 0.08);
    --shadow-color: rgba(0, 0, 0, 0.2);
}

.footer {
    background: linear-gradient(180deg, var(--footer-bg-start), var(--footer-bg-end));
    color: var(--footer-text);
    margin-top: auto;
    padding-top: 24px;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
    border-top: 1px solid var(--border-color);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    position: relative;
    z-index: 10;
}

/* Layout */
.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    padding: 40px 24px;
    max-width: 1200px;
    margin: 0 auto;
    align-items: start;
}

/* Section styling */
.footer-section {
    padding: 5px 10px;
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.footer-section:hover {
    transform: translateY(-3px);
    opacity: 1;
}

/* Headings */
.footer-section h4 {
    color: var(--primary-light);
    margin-bottom: 16px;
    font-size: 1.1rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    position: relative;
    padding-bottom: 10px;
    font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.footer-section h4::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 40px;
    height: 2px;
    background-color: var(--primary-color);
}

/* Logo area */
.footer-logo {
    width: 100%;
    height: auto;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    font-size: 1.25rem;
    font-weight: 700;
}

.footer-logo img {
    width: 150px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border: 1px solid var(--border-color);
}

.footer-logo span {
    color: var(--footer-text);
    font-size: 1.05rem;
    line-height: 1.2;
}

.footer-description {
    line-height: 1.6;
    margin-bottom: 20px;
    color: var(--footer-muted);
    font-size: 0.95rem;
}

/* Social icons */
.footer-social {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin-top: 8px;
}

.social-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    background-color: rgba(255, 255, 255, 0.08);
    border-radius: 50%;
    color: var(--footer-text);
    text-decoration: none;
    transition: all 0.25s ease;
}

.social-link:hover,
.social-link:focus {
    background-color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    outline: none;
}

/* Links styling */
.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 12px;
}

.footer-links a {
    color: var(--footer-quiet);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
    padding: 8px 0;
    font-size: 0.95rem;
    letter-spacing: 0.2px;
}

.footer-links a i {
    color: var(--primary-light);
    width: 16px;
    text-align: center;
    font-size: 1rem;
}

.contact-info a:hover,
.contact-info a:focus,
.footer-links a:hover,
.footer-links a:focus {
    color: var(--footer-text);
    transform: translateX(3px);
}

/* Contact & system info */
.contact-info p,
.system-info p {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    color: var(--footer-quiet);
    font-size: 0.95rem;
    padding: 3px 0;
}

.contact-info i,
.system-info i {
    color: var(--primary-light);
    width: 18px;
    text-align: center;
}

.contact-info a {
    color: var(--footer-quiet);
    text-decoration: none;
    transition: color 0.2s ease;
}

.contact-info a:hover {
    color: var(--footer-text);
}

/* Visual status */
.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 8px 12px;
    width: fit-content;
    background-color: rgba(46, 204, 113, 0.1);
    border-radius: 6px;
    border-left: 3px solid var(--success-color);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.15);
}

.status-dot.online {
    background-color: var(--success-color);
}

/* Footer bottom */
.footer-bottom {
    border-top: 1px solid var(--border-color);
    padding: 20px 0;
    margin-top: 10px;
    background-color: rgba(0, 0, 0, 0.1);
}

.footer-bottom-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
    flex-wrap: wrap;
    gap: 15px;
}

.footer-bottom p {
    color: var(--footer-muted);
    margin: 0;
    font-size: 0.9rem;
}

.footer-bottom-links {
    display: flex;
    gap: 20px;
}

.footer-bottom-links a {
    color: var(--footer-quiet);
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.2s ease;
    position: relative;
}

.footer-bottom-links a:not(:last-child)::after {
    content: '•';
    position: absolute;
    right: -12px;
    color: var(--footer-quiet);
    opacity: 0.5;
}

.footer-bottom-links a:hover,
.footer-bottom-links a:focus {
    color: var(--primary-light);
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .footer-content {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 25px;
        padding: 35px 20px;
    }
}

@media (max-width: 768px) {
    .footer-content {
        padding: 30px 18px;
        grid-auto-rows: auto;
        gap: 30px;
    }
    
    .footer-bottom-content {
        flex-direction: column;
        text-align: center;
        gap: 12px;
        padding: 0 18px;
    }
    
    .footer-social {
        justify-content: center;
    }
    
    .footer-section h4::after {
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
    }
    
    .footer-section h4,
    .contact-info p,
    .system-info p {
        text-align: center;
    }
    
    .contact-info p,
    .system-info p {
        justify-content: center;
    }
    
    .footer-links a {
        justify-content: center;
    }
    
    .status-indicator {
        margin: 12px auto 0;
    }
}

@media (max-width: 480px) {
    .footer-content {
        padding: 25px 15px;
        gap: 25px;
    }
    
    .footer-logo {
        flex-direction: column;
        text-align: center;
    }
    
    .footer-logo img {
        width: 120px;
        height: 40px;
    }
    
    .footer-section h4 {
        font-size: 1rem;
    }
    
    .footer-bottom-links {
        flex-direction: column;
        gap: 10px;
    }
    
    .footer-bottom-links a:not(:last-child)::after {
        display: none;
    }
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