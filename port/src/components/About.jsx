
import React from 'react';
import SectionWrapper from './SectionWrapper';
import { motion } from 'framer-motion';
import { UserCircle, Briefcase, BookOpen } from 'lucide-react';

const AboutContent = () => {
  const textVariant = (delay) => ({
    hidden: { y: 20, opacity: 0 },
    show: { y: 0, opacity: 1, transition: { type: 'spring', duration: 1.25, delay } },
  });

  const imageVariant = {
    hidden: { scale: 0.8, opacity: 0 },
    show: { scale: 1, opacity: 1, transition: { type: 'spring', duration: 1, delay: 0.2 } },
  };

  return (
    <div className="flex flex-col lg:flex-row items-center gap-12 lg:gap-16">
      <motion.div 
        className="w-full lg:w-2/5 flex justify-center"
        variants={imageVariant}
      >
        <div className="relative w-64 h-64 sm:w-80 sm:h-80 rounded-full overflow-hidden shadow-2xl border-4 border-primary p-2 bg-gradient-to-br from-primary to-secondary">
          <img  
            className="w-full h-full object-cover rounded-full" 
            alt="Kellyson Raphael - Desenvolvedor Fullstack"
           src="https://images.unsplash.com/photo-1689942009554-759940987be0" />
          <motion.div 
            className="absolute inset-0 border-4 border-accent rounded-full"
            animate={{ rotate: 360 }}
            transition={{ duration: 20, repeat: Infinity, ease: "linear" }}
          />
        </div>
      </motion.div>
      <div className="w-full lg:w-3/5">
        <motion.h2 
          variants={textVariant(0)} 
          className="section-title !text-left mb-6"
        >
          Sobre Mim
        </motion.h2>
        <motion.p variants={textVariant(0.2)} className="text-lg text-foreground/80 mb-6 leading-relaxed">
          Sempre fui apaixonado por tecnologia. Desde pequeno, ficava curioso sobre como as coisas funcionavam por trás das telas, e hoje tenho a chance de colocar isso em prática.
        </motion.p>
        <motion.p variants={textVariant(0.4)} className="text-lg text-foreground/80 mb-8 leading-relaxed">
          Na Prefeitura de Pau dos Ferros, trabalho em projetos que impactam o dia a dia das pessoas, como sistemas de gestão de estágios e protocolos. Cada linha de código que escrevo é uma oportunidade de aprender algo novo e fazer a diferença.
        </motion.p>
        
        <motion.div variants={textVariant(0.6)} className="space-y-4">
          <div className="flex items-start space-x-3 p-4 glassmorphism-card rounded-lg">
            <Briefcase className="text-accent w-8 h-8 mt-1 shrink-0" />
            <div>
              <h4 className="font-semibold text-xl text-primary">Experiência Profissional</h4>
              <p className="text-foreground/80">Desenvolvedor na Prefeitura de Pau dos Ferros, focado em sistemas web e móveis para otimizar processos administrativos.</p>
            </div>
          </div>
          <div className="flex items-start space-x-3 p-4 glassmorphism-card rounded-lg">
            <BookOpen className="text-accent w-8 h-8 mt-1 shrink-0" />
            <div>
              <h4 className="font-semibold text-xl text-primary">Formação Acadêmica</h4>
              <p className="text-foreground/80">Cursando Análise e Desenvolvimento de Sistemas no IFRN - Pau dos Ferros.</p>
            </div>
          </div>
        </motion.div>
      </div>
    </div>
  );
};

const About = SectionWrapper(AboutContent, 'about');
export default About;
  