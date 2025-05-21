
import React from 'react';
import { motion } from 'framer-motion';
import { Code, Heart } from 'lucide-react';

const Footer = () => {
  const year = new Date().getFullYear();

  return (
    <motion.footer 
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      transition={{ duration: 0.5, delay: 0.2 }}
      className="bg-background/50 border-t border-border/30 py-8 text-center"
    >
      <div className="container mx-auto px-4">
        <div className="flex flex-col items-center justify-center space-y-3">
          <motion.div 
            className="flex items-center space-x-2 text-primary"
            animate={{ y: [0, -5, 0] }}
            transition={{ duration: 1.5, repeat: Infinity, ease: "easeInOut" }}
          >
            <Code size={24} />
          </motion.div>
          <p className="text-sm text-muted-foreground flex items-center justify-center">
            Desenvolvido com <Heart size={16} className="text-red-500 mx-1.5 fill-current" /> por 
            <a href="https://github.com/Kellyson71" target="_blank" rel="noopener noreferrer" className="font-semibold text-primary hover:text-accent transition-colors ml-1">
              Kellyson Raphael
            </a>.
          </p>
          <p className="text-xs text-muted-foreground/70">
            &copy; {year} Todos os direitos reservados.
          </p>
        </div>
      </div>
    </motion.footer>
  );
};

export default Footer;
  