
import React from 'react';
import { motion } from 'framer-motion';

const SectionWrapper = (Component, idName) => 
  function HOC() {
    return (
      <motion.section
        variants={{
          hidden: { opacity: 0, y: 50 },
          show: { 
            opacity: 1, 
            y: 0,
            transition: {
              type: 'spring',
              stiffness: 50,
              duration: 0.75,
              delay: 0.1
            }
          }
        }}
        initial="hidden"
        whileInView="show"
        viewport={{ once: true, amount: 0.25 }}
        className="container mx-auto px-4 py-16 md:py-24 relative"
        id={idName}
      >
        <span className="hash-span" id={idName}>&nbsp;</span>
        <Component />
      </motion.section>
    );
  };

export default SectionWrapper;
  