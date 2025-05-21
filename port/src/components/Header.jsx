
import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Menu, X, Code } from 'lucide-react';
import { Button } from '@/components/ui/button';

const navLinks = [
  { href: '#home', label: 'Início' },
  { href: '#about', label: 'Sobre Mim' },
  { href: '#skills', label: 'Habilidades' },
  { href: '#projects', label: 'Projetos' },
  { href: '#education', label: 'Educação' },
  { href: '#contact', label: 'Contato' },
];

const Header = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 50);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const toggleMenu = () => setIsOpen(!isOpen);

  const scrollToSection = (e, href) => {
    e.preventDefault();
    document.querySelector(href).scrollIntoView({ behavior: 'smooth' });
    if (isOpen) setIsOpen(false);
  };

  const headerVariants = {
    initial: { y: -100, opacity: 0 },
    animate: { y: 0, opacity: 1, transition: { type: 'spring', stiffness: 120, damping: 20 } },
  };

  const navItemVariants = {
    initial: { y: -20, opacity: 0 },
    animate: { y: 0, opacity: 1 },
  };

  return (
    <motion.header
      variants={headerVariants}
      initial="initial"
      animate="animate"
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        isScrolled || isOpen ? 'bg-background/80 backdrop-blur-md shadow-lg' : 'bg-transparent'
      }`}
    >
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-20">
          <motion.a 
            href="#home" 
            onClick={(e) => scrollToSection(e, "#home")}
            className="flex items-center space-x-2 text-2xl font-bold text-gradient"
            whileHover={{ scale: 1.05, textShadow: "0px 0px 8px hsl(var(--primary))" }}
          >
            <Code size={28} className="text-primary"/>
            <span>Kellyson R.</span>
          </motion.a>

          <nav className="hidden md:flex space-x-2">
            {navLinks.map((link, index) => (
              <motion.div key={link.href} variants={navItemVariants} transition={{ delay: index * 0.1 }}>
                <Button variant="ghost" asChild>
                  <a href={link.href} onClick={(e) => scrollToSection(e, link.href)} className="px-3 py-2 rounded-md text-sm font-medium text-foreground hover:text-primary transition-colors">
                    {link.label}
                  </a>
                </Button>
              </motion.div>
            ))}
          </nav>

          <div className="md:hidden">
            <Button variant="ghost" size="icon" onClick={toggleMenu} aria-label="Abrir menu">
              {isOpen ? <X size={24} /> : <Menu size={24} />}
            </Button>
          </div>
        </div>
      </div>

      {isOpen && (
        <motion.div
          initial={{ opacity: 0, height: 0 }}
          animate={{ opacity: 1, height: 'auto' }}
          exit={{ opacity: 0, height: 0 }}
          className="md:hidden bg-background/90 backdrop-blur-md pb-4"
        >
          <nav className="flex flex-col items-center space-y-2 px-4">
            {navLinks.map((link) => (
              <Button variant="ghost" asChild key={link.href} className="w-full">
                <a href={link.href} onClick={(e) => scrollToSection(e, link.href)} className="block px-3 py-3 rounded-md text-base font-medium text-foreground hover:text-primary transition-colors text-center">
                  {link.label}
                </a>
              </Button>
            ))}
          </nav>
        </motion.div>
      )}
    </motion.header>
  );
};

export default Header;
  