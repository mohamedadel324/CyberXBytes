<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CyberXbytes</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #38FFE5;
            --bg-dark: #0A0C10;
            --text-light: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }
        
        .container {
            text-align: center;
            z-index: 10;
            max-width: 90%;
        }
        
        h1 {
            font-size: 5.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            position: relative;
            animation: fadeIn 1s ease-in-out;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        h1 span {
            color: var(--primary-color);
            position: relative;
            display: inline-block;
            animation: glow 2s ease-in-out infinite alternate;
            text-shadow: 0 0 20px rgba(56, 255, 229, 0.7);
        }

        .letter {
            display: inline-block;
            opacity: 0;
            filter: blur(10px);
            transform: translateY(20px) scale(1.2);
            animation: blowIn 0.8s forwards;
        }
        
        .space {
            display: inline-block;
            width: 0.5em;
        }

        .main-text {
            font-size: 2.2rem;
            line-height: 1.5;
            max-width: 800px;
            margin: 0 auto 3rem;
            text-align: center;
            font-weight: 500;
        }
        
        .buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            background-color: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            padding: 1rem 2.5rem;
            font-size: 1.2rem;
            font-weight: 500;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            opacity: 0;
            animation: fadeIn 1s ease-in-out forwards;
            animation-delay: 1s;
            z-index: 1;
            letter-spacing: 1px;
        }
        
        .btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(56, 255, 229, 0.2), transparent);
            transition: left 0.7s;
        }
        
        .btn:hover:before {
            left: 100%;
        }
        
        .btn:hover {
            background-color: var(--primary-color);
            color: var(--bg-dark);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(56, 255, 229, 0.3);
        }
        
        .btn:active {
            transform: translateY(-2px);
        }
        
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background-color: var(--primary-color);
            border-radius: 50%;
            opacity: 0.5;
            filter: blur(1px);
        }
        
        @keyframes glow {
            0% {
                text-shadow: 0 0 10px rgba(56, 255, 229, 0.7),
                             0 0 20px rgba(56, 255, 229, 0.5),
                             0 0 30px rgba(56, 255, 229, 0.3);
                color: var(--primary-color);
            }
            100% {
                text-shadow: 0 0 20px rgba(56, 255, 229, 0.9),
                             0 0 30px rgba(56, 255, 229, 0.7),
                             0 0 40px rgba(56, 255, 229, 0.5),
                             0 0 50px rgba(56, 255, 229, 0.3);
                color: #fff;
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes blowIn {
            0% {
                opacity: 0;
                filter: blur(10px);
                transform: translateY(20px) scale(1.2);
            }
            100% {
                opacity: 1;
                filter: blur(0);
                transform: translateY(0) scale(1);
            }
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 3.5rem;
            }
            
            .main-text {
                font-size: 1.3rem;
                padding: 0 20px;
            }
            
            .btn {
                padding: 0.8rem 2rem;
                font-size: 1.1rem;
                margin: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>
    
    <div class="container">
        <h1>Welcome to <span>CyberXbytes</span></h1>
        
        <div class="main-text" id="blowing-text">
            Join us at CyberXbytes to test your skills in the world of cybersecurity and compete with elite professionals and amateurs in an exciting gaming environment full of challenges.
        </div>
        
        <div class="buttons">
            <a href="/admin" class="btn">Go to Admin Panel</a>
        </div>
    </div>
    
    <script>
        // Create particles
        const particlesContainer = document.getElementById('particles');
        const particleCount = 80;
        
        for (let i = 0; i < particleCount; i++) {
            createParticle();
        }
        
        function createParticle() {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            
            // Random position
            const posX = Math.random() * window.innerWidth;
            const posY = Math.random() * window.innerHeight;
            
            // Random size
            const size = Math.random() * 3 + 1;
            
            // Set styles
            particle.style.left = `${posX}px`;
            particle.style.top = `${posY}px`;
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            particle.style.opacity = Math.random() * 0.5 + 0.3;
            
            // Animate
            particle.style.animation = `
                moveParticle ${Math.random() * 20 + 20}s linear infinite,
                fadeParticle ${Math.random() * 5 + 5}s ease-in-out infinite alternate
            `;
            
            // Add keyframes dynamically
            const keyframes = `
                @keyframes moveParticle {
                    0% {
                        transform: translate(0, 0);
                    }
                    33% {
                        transform: translate(${Math.random() * 200 - 100}px, ${Math.random() * 200 - 100}px);
                    }
                    66% {
                        transform: translate(${Math.random() * 200 - 100}px, ${Math.random() * 200 - 100}px);
                    }
                    100% {
                        transform: translate(0, 0);
                    }
                }
                
                @keyframes fadeParticle {
                    0% { opacity: ${Math.random() * 0.5 + 0.3}; }
                    100% { opacity: ${Math.random() * 0.3 + 0.1}; }
                }
            `;
            
            const style = document.createElement('style');
            style.textContent = keyframes;
            document.head.appendChild(style);
            
            particlesContainer.appendChild(particle);
        }

        // Text animation with proper spacing
        function animateText() {
            const text = document.getElementById('blowing-text');
            const textContent = text.textContent.trim();
            text.innerHTML = '';
            
            // Split text into words and then characters
            const words = textContent.split(' ');
            
            words.forEach((word, wordIndex) => {
                // Process each character in the word
                for (let i = 0; i < word.length; i++) {
                    const letter = document.createElement('span');
                    letter.classList.add('letter');
                    letter.textContent = word[i];
                    letter.style.animationDelay = `${0.03 * (i + wordIndex * word.length)}s`;
                    text.appendChild(letter);
                }
                
                // Add a space after each word (except the last one)
                if (wordIndex < words.length - 1) {
                    const space = document.createElement('span');
                    space.classList.add('space');
                    space.innerHTML = '&nbsp;';
                    text.appendChild(space);
                }
            });
        }
        
        // Initialize animations
        window.addEventListener('DOMContentLoaded', () => {
            animateText();
        });
    </script>
</body>
</html> 