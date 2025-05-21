
import React from 'react';
import SectionWrapper from './SectionWrapper';
import { motion } from 'framer-motion';
import { GraduationCap } from 'lucide-react';

const EducationContent = () => {
  const cardVariants = {
    hidden: { opacity: 0, x: -100 },
    show: { opacity: 1, x: 0, transition: { type: 'spring', stiffness: 50, duration: 0.8 } }
  };

  const textVariants = (delay) => ({
    hidden: { opacity: 0, y: 20 },
    show: { opacity: 1, y: 0, transition: { duration: 0.5, delay } }
  });

  return (
    <div className="flex flex-col items-center">
      <motion.h2 
        initial={{ opacity: 0, y: -20 }}
        whileInView={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        viewport={{ once: true }}
        className="section-title"
      >
        Educação
      </motion.h2>
      
      <motion.div 
        variants={cardVariants}
        className="w-full max-w-2xl p-8 glassmorphism-card rounded-xl shadow-xl"
      >
        <div className="flex flex-col sm:flex-row items-center text-center sm:text-left">
          <motion.div 
            className="p-4 bg-primary/20 rounded-full mb-6 sm:mb-0 sm:mr-6"
            animate={{ scale: [1, 1.1, 1] }}
            transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
          >
            <GraduationCap size={56} className="text-primary" />
          </motion.div>
          <div>
            <motion.h3 variants={textVariants(0.2)} className="text-2xl font-semibold text-primary mb-2">
              IFRN Pau dos Ferros
            </motion.h3>
            <motion.p variants={textVariants(0.4)} className="text-lg text-foreground/90 mb-1">
              Análise e Desenvolvimento de Sistemas
            </motion.p>
            <motion.p variants={textVariants(0.6)} className="text-md text-accent font-medium">
              Em andamento
            </motion.p>
            <motion.p variants={textVariants(0.8)} className="text-sm text-muted-foreground mt-3">
              No IFRN, estou aprofundando meus conhecimentos em desenvolvimento de software, arquitetura de sistemas e boas práticas de programação, preparando-me para os desafios do mercado de tecnologia.
            </motion.p>
          </div>
        </div>
      </motion.div>

      <motion.div 
        className="mt-12 w-full max-w-3xl text-center"
        initial={{ opacity:0, y:20 }}
        whileInView={{ opacity:1, y:0 }}
        transition={{ duration:0.5, delay: 0.5 }}
        viewport={{ once:true }}
      >
        <h3 className="text-xl font-semibold mb-3 text-gradient">Visão Geral do Perfil e Repositórios (GitHub)</h3>
        <p className="text-muted-foreground leading-relaxed">
          Kellyson Raphael, conhecido no GitHub como <a href="https://github.com/kellyson71" target="_blank" rel="noopener noreferrer" className="text-accent hover:underline">kellyson71</a>, é um desenvolvedor fullstack com um perfil que reflete sua paixão por tecnologia e aprendizado contínuo. Seus repositórios no GitHub mostram uma variedade de projetos que demonstram suas habilidades em várias tecnologias.
        </p>
        <div className="mt-4 p-4 bg-card/50 rounded-lg border border-border/30">
          <h4 className="font-semibold text-lg text-primary mb-2">Detalhes do Perfil GitHub</h4>
          <ul className="list-disc list-inside text-left text-foreground/80 space-y-1">
            <li><span className="font-medium">Nome de Usuário:</span> kellyson71</li>
            <li><span className="font-medium">Função:</span> Desenvolvedor Fullstack</li>
            <li><span className="font-medium">Foco:</span> Desenvolvimento de sistemas web e móveis com impacto positivo.</li>
          </ul>
        </div>
      </motion.div>
    </div>
  );
};

const Education = SectionWrapper(EducationContent, 'education');
export default Education;
  