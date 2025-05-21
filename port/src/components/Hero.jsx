
import React from 'react';
import { motion } from 'framer-motion';
import { Button } from '@/components/ui/button';
import { ArrowDown, Github, Linkedin, Mail } from 'lucide-react';
import { TypingText } from '@/components/AnimatedText';

const Hero = () => {
  const scrollToProjects = () => {
    document.querySelector('#projects')?.scrollIntoView({ behavior: 'smooth' });
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    show: {
      opacity: 1,
      transition: {
        staggerChildren: 0.2,
        delayChildren: 0.5,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    show: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 100 } },
  };
  
  const socialLinks = [
    { icon: <Github size={24} />, href: "https://github.com/Kellyson71", label: "GitHub" },
    { icon: <Linkedin size={24} />, href: "https://www.linkedin.com/in/kellyson-raphael/", label: "LinkedIn" },
    { icon: <Mail size={24} />, href: "mailto:kellyson.medeiros.pdf@gmail.com", label: "Email" },
  ];

  return (
    <section id="home" className="min-h-screen flex items-center justify-center relative overflow-hidden bg-gradient-to-br from-background via-background to-primary/10">
      <motion.div 
        className="absolute inset-0 opacity-20"
        animate={{
          backgroundPosition: ["0% 50%", "100% 50%", "0% 50%"],
        }}
        transition={{
          duration: 20,
          ease: "linear",
          repeat: Infinity,
        }}
        style={{
          backgroundImage: `radial-gradient(circle at 20% 20%, hsl(var(--primary)) 0%, transparent 30%),
                           radial-gradient(circle at 80% 70%, hsl(var(--accent)) 0%, transparent 30%)`,
          backgroundSize: "200% 200%",
        }}
      />
      
      <div className="container mx-auto px-4 text-center relative z-10">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="show"
        >
          <motion.h1 variants={itemVariants} className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black mb-4">
            <span className="text-gradient">Kellyson Raphael</span>
          </motion.h1>
          
          <TypingText 
            text="Desenvolvedor Fullstack"
            className="text-xl sm:text-2xl md:text-3xl text-muted-foreground mb-8 font-mono"
          />

          <motion.p variants={itemVariants} className="max-w-2xl mx-auto text-base sm:text-lg text-foreground/80 mb-10">
            Oi! Eu sou o Kellyson, um desenvolvedor Fullstack que adora transformar ideias em soluções reais através do código. Estou cursando Análise e Desenvolvimento de Sistemas no IFRN e trabalho na Prefeitura de Pau dos Ferros, onde desenvolvo sistemas web e mobile para tornar os processos administrativos mais práticos e eficientes.
          </motion.p>

          <motion.div variants={itemVariants} className="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6 mb-12">
            <Button size="lg" onClick={scrollToProjects} className="bg-gradient-to-r from-primary to-secondary hover:opacity-90 transition-opacity duration-300 shadow-lg transform hover:scale-105">
              Ver Projetos <ArrowDown size={20} className="ml-2" />
            </Button>
            <Button size="lg" variant="outline" asChild className="border-primary text-primary hover:bg-primary/10 hover:text-primary transition-colors duration-300 shadow-lg transform hover:scale-105">
              <a href="#contact">Entrar em Contato</a>
            </Button>
          </motion.div>

          <motion.div variants={itemVariants} className="flex justify-center space-x-6">
            {socialLinks.map(link => (
              <motion.a 
                key={link.label} 
                href={link.href} 
                target="_blank" 
                rel="noopener noreferrer" 
                aria-label={link.label}
                className="text-muted-foreground hover:text-primary transition-colors duration-300"
                whileHover={{ y: -3, scale: 1.1 }}
              >
                {link.icon}
              </motion.a>
            ))}
          </motion.div>
        </motion.div>
      </div>
       <motion.div 
        className="absolute bottom-10 left-1/2 transform -translate-x-1/2 animate-bounce"
        initial={{ opacity: 0, y:20 }}
        animate={{ opacity: 1, y:0 }}
        transition={{ delay: 2, duration: 0.5, repeat: Infinity, repeatType: "reverse", ease: "easeInOut" }}
      >
        <ArrowDown size={28} className="text-primary" />
      </motion.div>
    </section>
  );
};

export default Hero;
  