import React from 'react';
import SectionWrapper from './SectionWrapper';
import { Button } from '@/components/ui/button';
import { Mail, Linkedin, Github, Smartphone, Zap } from 'lucide-react';
import { motion } from 'framer-motion';

const contactMethods = [
  {
    icon: <Mail size={28} className="text-primary group-hover:text-accent transition-colors" />,
    label: 'Email',
    value: 'kellyson.medeiros.pdf@gmail.com',
    href: 'mailto:kellyson.medeiros.pdf@gmail.com',
  },
  {
    icon: <Linkedin size={28} className="text-primary group-hover:text-accent transition-colors" />,
    label: 'LinkedIn',
    value: 'Kellyson Raphael',
    href: 'https://www.linkedin.com/in/kellyson-raphael/',
  },
  {
    icon: <Github size={28} className="text-primary group-hover:text-accent transition-colors" />,
    label: 'GitHub',
    value: 'Kellyson71',
    href: 'https://github.com/Kellyson71',
  },
  {
    icon: <Zap size={28} className="text-primary group-hover:text-accent transition-colors" />,
    label: 'WhatsApp',
    value: '+55 84 8108-7357',
    href: 'https://wa.me/558481087357',
  },
];

const ContactContent = () => {
  const containerVariants = {
    hidden: { opacity: 0 },
    show: {
      opacity: 1,
      transition: { staggerChildren: 0.15, delayChildren: 0.3 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 30, scale: 0.9 },
    show: { opacity: 1, y: 0, scale: 1, transition: { type: 'spring', stiffness: 80 } },
  };

  return (
    <div className="text-center">
      <motion.h2 
        initial={{ opacity: 0, y: -20 }}
        whileInView={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        viewport={{ once: true }}
        className="section-title"
      >
        Entre em Contato
      </motion.h2>
      <motion.p 
        initial={{ opacity: 0, y:20 }}
        whileInView={{ opacity: 1, y:0 }}
        transition={{ duration: 0.5, delay: 0.2 }}
        viewport={{ once: true }}
        className="text-lg text-muted-foreground mb-12 max-w-xl mx-auto"
      >
        Adoraria ouvir sobre seus projetos e ideias. Se quiser falar comigo, é só me chamar:
      </motion.p>

      <motion.div 
        className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12"
        variants={containerVariants}
        initial="hidden"
        whileInView="show"
        viewport={{ once: true, amount: 0.2 }}
      >
        {contactMethods.map((method) => (
          <motion.a
            key={method.label}
            href={method.href}
            target="_blank"
            rel="noopener noreferrer"
            variants={itemVariants}
            className="group block p-6 sm:p-8 glassmorphism-card rounded-xl shadow-lg hover:shadow-primary/40 transition-all duration-300 transform hover:-translate-y-2"
            whileHover={{ scale: 1.03 }}
          >
            <div className="flex flex-col items-center">
              <div className="mb-4 p-3 rounded-full bg-primary/10 group-hover:bg-accent/10 transition-colors">{method.icon}</div>
              <h3 className="text-lg sm:text-xl font-semibold text-primary mb-1 group-hover:text-accent transition-colors">{method.label}</h3>
              <p className="text-sm sm:text-base text-foreground/80 break-words">{method.value}</p>
            </div>
          </motion.a>
        ))}
      </motion.div>
      
      <motion.div
        initial={{ opacity: 0, scale: 0.8 }}
        whileInView={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.5, delay: 0.5 }}
        viewport={{ once: true }}
        className="flex flex-col sm:flex-row justify-center items-center gap-4"
      >
        <Button size="lg" asChild className="bg-gradient-to-r from-primary to-secondary hover:opacity-90 transition-opacity duration-300 shadow-xl transform hover:scale-105 px-8 py-5 text-md w-full sm:w-auto">
          <a href="mailto:kellyson.medeiros.pdf@gmail.com">
            Enviar um Email <Mail size={20} className="ml-2" />
          </a>
        </Button>
        <Button size="lg" variant="outline" asChild className="border-accent text-accent hover:bg-accent/10 hover:text-accent transition-colors duration-300 shadow-lg transform hover:scale-105 px-8 py-5 text-md w-full sm:w-auto">
            <a href="https://wa.me/558481087357" target="_blank" rel="noopener noreferrer">
                Chamar no WhatsApp <Zap size={20} className="ml-2" />
            </a>
        </Button>
      </motion.div>
    </div>
  );
};

const Contact = SectionWrapper(ContactContent, 'contact');
export default Contact;