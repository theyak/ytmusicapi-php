// Mobile navigation toggle
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');
    
    if (hamburger) {
        hamburger.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Auto-generate table of contents for API pages
    const content = document.querySelector('.content');
    const sidebar = document.querySelector('.sidebar ul');
    
    if (content && sidebar) {
        const headings = content.querySelectorAll('h2, h3');
        
        headings.forEach((heading, index) => {
            // Add ID to heading if it doesn't have one
            if (!heading.id) {
                heading.id = heading.textContent.toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-');
            }
            
            // Create sidebar link
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = '#' + heading.id;
            a.textContent = heading.textContent;
            a.style.paddingLeft = heading.tagName === 'H3' ? '20px' : '8px';
            li.appendChild(a);
            sidebar.appendChild(li);
        });
    }

});

// Copy code functionality
function copyCode(button) {
    const codeBlock = button.parentElement.querySelector('code');
    const text = codeBlock.textContent;
    
    navigator.clipboard.writeText(text).then(() => {
        button.textContent = 'Copied!';
        setTimeout(() => {
            button.textContent = 'Copy';
        }, 2000);
    });
}

// Add copy buttons to code blocks
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('pre code').forEach(codeBlock => {
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        
        const copyBtn = document.createElement('button');
        copyBtn.textContent = 'Copy';
        copyBtn.style.cssText = `
            position: absolute;
            top: 10px;
            right: 10px;
            background: #FF0000;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        `;
        copyBtn.onclick = () => copyCode(copyBtn);
        
        codeBlock.parentElement.parentElement.insertBefore(wrapper, codeBlock.parentElement);
        wrapper.appendChild(codeBlock.parentElement);
        wrapper.appendChild(copyBtn);
        
        wrapper.addEventListener('mouseenter', () => {
            copyBtn.style.opacity = '1';
        });
        
        wrapper.addEventListener('mouseleave', () => {
            copyBtn.style.opacity = '0';
        });
    });
});

// Add active state to sidebar links
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        .sidebar ul li a.active {
            background: #FF0000 !important;
            color: white !important;
        }
    `;
    document.head.appendChild(style);
});
